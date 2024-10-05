<?php
namespace Concept\Di\Factory;


use ReflectionMethod;
use ReflectionClass;
use ReflectionNamedType;
use Psr\Container\ContainerInterface;
use Concept\Config\ConfigInterface;
use Concept\Di\Factory\Exception\LogicException;
use Concept\Di\Factory\Exception\InvalidArgumentException;

class DiFactory implements DiFactoryInterface
{
    protected ?ConfigInterface $config = null;
    protected ?ContainerInterface $container = null;

    protected ?string $serviceId = null;
    protected ?array $parameters = null;

    protected ?ReflectionClass $serviceReflection = null;
    protected ?ConfigInterface $serviceConfig = null;

    protected array $dependencyStack = [];
    protected ?string $resolvedServiceClass = null;
    protected $service = null;

    public function __construct(?ConfigInterface $config = null, ?ContainerInterface $container = null)
    {
        $this->config = $config;
        $this->container = $container;
    }

    public function reset()
    {
        $excluded = ['config', 'container', 'dependencyStack'];

        foreach (get_object_vars($this) as $property => $value) {
            if (!in_array($property, $excluded)) {
                $this->$property = null;
            }
        }
    }

    public function __clone()
    {
        $this->reset();
    }

    public function withConfig(ConfigInterface $config): self
    {
        $clone = clone $this;
        $clone->config = $config;

        return $clone;
    }
    
    public function withContainer(ContainerInterface $container): self
    {
        $clone = clone $this;
        $clone->container = $container;

        return $clone;
    }

    protected function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    protected function getConfig(): ConfigInterface
    {
        if (null === $this->config) {
            throw new \RuntimeException('Config is not set');
        }

        return $this->config;// ?? $this->create(ConfigInterface::class);
    }

    protected function getServiceId(): string
    {
        return $this->serviceId;
    }

    protected function getParameters(): ?array
    {
        return $this->parameters;
    }

    protected function assertState(): self
    {
        // if (null === $this->container) {
        //     throw new InvalidArgumentException('Container is not set');
        // }

        if (null === $this->serviceId) {
            throw new InvalidArgumentException('Service ID is not set');
        }

        if ($this->isInDependencyStack($this->getServiceId())) {
            throw new LogicException(
                sprintf(_('Circular dependency detected for service "%s"'), $this->getServiceId())
            );
        }

        return $this;
    }

    public function create(string $serviceId, ...$parameters): object
    {
        /**
         * @todo: singleton should be here?
         */
        if ($this->getContainer() && $this->getContainer()->has($serviceId)) {
            return $this->getContainer()->get($serviceId);
        }

        $this->reset();

        $this->serviceId = $serviceId;
        $this->parameters = $parameters;

        $this
            ->assertState()
            ->createService();

        return $this->getService();
    }

    protected function createService(): self
    {
        $this
            ->addDependencyStack($this->getServiceId())
            ->applyConfigContext()
            ->applyServiceRuntimeContext();

        $reflection = $this->getServiceReflection();

        if (!$reflection->isInstantiable()) {
            throw new LogicException(
                sprintf(_('Class "%s" is not instantiable'), $this->getServiceClass())
            );
        }

        if (!$reflection->getConstructor() instanceof ReflectionMethod) {
            /**
             * No constructor
             */
            $this->service = $reflection->newInstanceWithoutConstructor();
        } else {
            /**
             * Constructor
             */
            $parameters = $this->resolveParameters($reflection->getConstructor());
            $this->service = $reflection->newInstance(...$parameters);
        }

        $this
            ->diAddons()
            ->applyServiceLifeCycle()
            ->restoreConfigContext()
            ->removeDependencyStack($this->getServiceId());

        return $this;
    }

    protected function diAddons(): self
    {
        $reflection = $this->getServiceReflection();

        if (!$reflection->hasMethod(DiFactoryInterface::DI_METHOD)) {
            return $this;
        }

        $method = $reflection->getMethod(DiFactoryInterface::DI_METHOD);
        $parameters = $this->resolveParameters($method);
        $method->setAccessible(true);
        $method->invoke($this->getService(), ...$parameters);

        return $this;
    }

    protected function applyServiceLifeCycle(): self
    {
        $preferenceConfig = $this->getServiceConfig();

        if ($preferenceConfig->get(DiFactoryInterface::NODE_SINGLETON)) {
            if(null !== $container = $this->getContainer()) {
                if (method_exists($container, 'attach')) {
                    /**
                     * @todo container interface?
                     */
                    $container->attach($this->getServiceId(), $this->getService());
                }
            }
        }

        return $this;
    }

    protected function getService(): object
    {
        if (null === $this->service) {
            throw new LogicException('Service is not created yet');
        }

        return $this->service;
    }

    protected function applyConfigContext(): self
    {
        $this->getConfig()
            ->pushState();

        $preferencePath = $this->getConfig()
            ->createPath(DiFactoryInterface::NODE_PREFERENCE, $this->getServiceId());

        if (!$this->getConfig()->has($preferencePath)) {
            /**
             * No preference found, return the default configuration
             * Service ID is the class name
             */
            $this->getConfig()->merge([
                DiFactoryInterface::NODE_PREFERENCE => [
                    $this->getServiceId() => [
                        DiFactoryInterface::NODE_CLASS => $this->getServiceId()
                    ]
                ]
            ]);

            return $this;
        }

        /**
         * Preference config
         */
        $config = $this->getConfig()
            ->fromPath($preferencePath);

        if ($config->has(DiFactoryInterface::NODE_REFERENCE)) {
            /**
             * Resolve reference
             */
            $referencePath = $this->getConfig()
                ->createPath(DiFactoryInterface::NODE_PREFERENCE, $config->get(DiFactoryInterface::NODE_REFERENCE));

            if (!$this->getConfig()->has($referencePath)) {
                throw new LogicException(
                    sprintf(_('Reference path not found "%s"'), $referencePath)
                );
            }

            /**
             * Apply reference config to the preference config
             */
            $config->merge( 
                $this->getConfig()
                    ->get($referencePath) 
            );

            $config->unset(DiFactoryInterface::NODE_REFERENCE);
        }

        /**
         * If the preference config has a DI config, merge it
         * This means overriding the Factory config with the Service DI config
         */
        if ($config->has(DiFactoryInterface::NODE_DI_CONFIG)) {
            $this->getConfig()
                ->merge(
                    $config->get(DiFactoryInterface::NODE_DI_CONFIG)
                );
        }

        if (!$config->has(DiFactoryInterface::NODE_CLASS)) {
            /**
             * No class found, use the service ID
             */
            $config->merge(
                [
                    DiFactoryInterface::NODE_CLASS => $this->getServiceId()
                ]
            );
        }


        /**
         * Apply resolved preference config to the factory config
         */
        $this->getConfig()
            ->merge([
                    DiFactoryInterface::NODE_PREFERENCE => [
                        $this->getServiceId() => $config->asArray()
                    ]
            ]);
        
        /**
         * @todo hash for singleton
         */
        return $this;
    }

    protected function applyServiceRuntimeContext(): self
    {
        $reflection = $this->getServiceReflection();

        /**
         * Inline DI config.
         * This is a constant in the service class
         * It must be an array
         * Config structure is the same as the main config
         */
        if ($reflection->hasConstant(DiFactoryInterface::INLINE_DI_CONFIG_CONSTANT)) {
            $diConfig = $reflection->getConstant(DiFactoryInterface::INLINE_DI_CONFIG_CONSTANT);
            if (!is_array($diConfig)) {
                throw new LogicException(
                    sprintf(_('Constant "%s" must be an array'), DiFactoryInterface::INLINE_DI_CONFIG_CONSTANT)
                );
            }

            $this->getConfig()
                ->merge($diConfig);
        }

        /**
         * Inline DI config file.
         * This is a constant in the service class
         * It must be a string
         * The file is relative to the service class file
         * The file must contain a valid JSON
         * JSON structure is the same as the main config
         */
        if ($reflection->hasConstant(DiFactoryInterface::INLINE_DI_CONFIG_FILE_CONSTANT)) {
            $diConfigFile = $reflection->getConstant(DiFactoryInterface::INLINE_DI_CONFIG_FILE_CONSTANT);
            if (!is_string($diConfigFile)) {
                throw new LogicException(
                    sprintf(_('Constant "%s" must be a string'), DiFactoryInterface::INLINE_DI_CONFIG_FILE_CONSTANT)
                );
            }
            
            $file = join(
                DIRECTORY_SEPARATOR,
                [
                    dirname($reflection->getFileName()),
                    $diConfigFile
                    ]
            );
            /**
             * @todo:vg data provider
             */
            $diConfig = json_decode(file_get_contents($file), true);
            
            $this->getConfig()
                ->merge($diConfig);
        }
        
        /**
         * Inline DI config class method.
         * This is a constant in the service class
         * It must be a string and the method with this name must exist in the service class
         * The method must be static
         * The method must return an array
         * The array structure is the same as the main config
         */
        if ($reflection->hasConstant(DiFactoryInterface::DYNAMIC_DI_CONFIG_METHOD)) {
            $method = $reflection->getConstant(DiFactoryInterface::DYNAMIC_DI_CONFIG_METHOD);
            if ($reflection->hasMethod($method)) {
               
                $methodReflection = $reflection->getMethod($method);
                if (!$methodReflection->isStatic()) {
                    throw new LogicException(
                        sprintf(_('Method "%s" must be static in class "%s"'), $method, $reflection->getName())
                    );
                }

                $methodReflection->setAccessible(true);
                $diConfig = $methodReflection->invoke(null);
                if (!is_array($diConfig)) {
                    throw new LogicException(
                        sprintf(_('Method "%s" must return an array in class "%s"'), $method, $reflection->getName())
                    );
                }

                $this->getConfig()
                    ->merge($diConfig);
            }
        }

        return $this;
    }

    protected function getServiceConfig(): ConfigInterface
    {
        if (null === $this->serviceConfig) {
            $this->serviceConfig = $this
                ->getConfig()
                    ->fromPath(
                        $this->getConfig()
                            ->createPath(DiFactoryInterface::NODE_PREFERENCE, $this->getServiceId())
                    );
        }

        return $this->serviceConfig;
    }

    protected function getServiceClass(): string
    {
        if (null === $this->resolvedServiceClass) {
            $this->resolvedServiceClass = $this->getServiceConfig()->get(DiFactoryInterface::NODE_CLASS);
        }

        return $this->resolvedServiceClass;
    }

    protected function getServiceReflection(): ReflectionClass
    {
        if (null === $this->serviceReflection) {
            $this->serviceReflection = new ReflectionClass($this->getServiceClass());
        }

        return $this->serviceReflection;
    }

    protected function getServiceParametersConfig(): ConfigInterface
    {
        $preferenceConfig = $this->getServiceConfig();

        if (!$preferenceConfig->has(DiFactoryInterface::NODE_PARAMETERS)) {
            return $preferenceConfig->withData([]);
        }

        return $preferenceConfig->fromPath(DiFactoryInterface::NODE_PARAMETERS);
    }

    protected function resolveParameters(ReflectionMethod $method): array
    {
        $passedParameters = $this->getParameters();
        if (!empty($passedParameters)) {
            /**
             * Parameters are passed explicitly
             */
            return $passedParameters;
        }

        $methodParameters = $method->getParameters();
        if (empty($methodParameters)) {
            /**
             * No parameters need to be resolved
             */
            return [];
        }

        /**
         * Preference parameters configuration
         */
        $parametersConfig = $this->getServiceParametersConfig();

        $parameters = [];

        

        foreach ($methodParameters as $parameter) {
            if ($parametersConfig->has($parameter->getName())) {
                /**
                 * Parameter is configured
                 */
                $parameters[] = $parametersConfig->get($parameter->getName(), DiFactoryInterface::NODE_PARAMETER_VALUE);
                continue;
            }

            if ($parameter->isOptional()) {
                /**
                 * Parameter is optional. Use the default value
                 */
                $parameters[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->isVariadic()) {
                throw new LogicException(
                    sprintf(
                        _('Variadic parameters are not supported. Method: %s'),
                        $method->getName()
                    )
                );
                $parameters = array_merge($parameters, $passedParameters);
                continue;
            }

            $parameterType = $parameter->getType();
            if (!$parameterType instanceof ReflectionNamedType) {
                throw new LogicException(
                    sprintf(_('Parameter "%s" has no named type. Currently is not supported. Method: %s'), $parameter->getName(), $method->getName())
                );
            }

            $typeName = $parameterType->getName();

            if (empty($typeName)) {
                throw new LogicException(
                    sprintf(_('Parameter "%s" is not typed'), $parameter->getName())
                );
            }

            /**
             * Does the service existance mean it is a singleton?
             * If no than @todo: check if the type is a singleton service!
             * 
             * Be careful with cloning the factory?
             */
            $parameters[] = $this->getContainer()->has($typeName) 
                ? $this->getContainer()->get($typeName) 
                : (clone $this)->create($typeName);
                /**
                 * clone or new self?
                 * if new than config state colund be cloned?
                 */
                //: (new self($this->getConfig(), $this->getContainer()))->create($typeName);
        }

        return $parameters;
    }


    protected function restoreConfigContext(): self
    {
        $this->getConfig()
            ->popState();

        return $this;
    }

    protected function addDependencyStack(string $serviceId): self
    {
        $this->dependencyStack[$serviceId] = true;

        return $this;
    }

    protected function isInDependencyStack(string $serviceId): bool
    {
        return array_key_exists($serviceId, $this->dependencyStack);
    }

    protected function removeDependencyStack(string $serviceId): self
    {
        unset($this->dependencyStack[$serviceId]);

        return $this;
    }
}