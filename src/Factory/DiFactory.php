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

    protected array $serviceStack = [];
    protected ?string $resolvedServiceClass = null;
    protected $service = null;

    public function __construct(?ConfigInterface $config = null, ?ContainerInterface $container = null)
    {
        $this->config = $config;
        $this->container = $container;
    }

    public function reset()
    {
        $excluded = ['config', 'container', 'serviceStack'];

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

        if ($this->isInServiceStack($this->getServiceId())) {
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
            ->addServiceStack($this->getServiceId())
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
            ->removeServiceStack($this->getServiceId());

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
        $this
            ->getConfig()
                ->pushState();
        
        $this->applyNamespaceContext();
            

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
        $config = $this
            ->getConfig()
                ->fromPath($preferencePath);

        /**
         * Apply dependency context
         */
        $this->applyDependencyContext($config);

        /**
         * Apply reference context
         */
        $this->applyReferenceContext($config);

        /**
         * Apply sub DI context
         */
        $this->applySubDiContext($config);

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
        $this
            ->getConfig()
                ->merge([
                        DiFactoryInterface::NODE_PREFERENCE => [
                            $this->getServiceId() => $config->asArray()
                        ]
                ]);
        
        return $this;
    }

    protected function applyNamespaceContext(): self
    {
        $namespaceParts = explode('\\', $this->getServiceId());
        $namespace = '';
        while (count($namespaceParts) > 0) {
            $namespace = join('\\', [$namespace, array_shift($namespaceParts)]);
            $namespacePath = $this->getConfig()
                ->createPath(DiFactoryInterface::NODE_NAMESPACE, $namespace);
            
            if ($this->getConfig()->has($namespacePath) && !$this->getConfig()->get($namespacePath, 'applied')) 
            {
                $namespaceConfig = $this->getConfig()
                    ->fromPath($namespacePath);
                
                /**
                 * Apply dependency context recursively
                 */
                $this->applyDependencyContext($namespaceConfig);

                $this->getConfig()
                    ->merge($namespaceConfig->asArray());
                
                $this->getConfig()
                    ->set($namespacePath.'.applied', true);
            }
        }

        return $this;
    }

    protected function applyDependencyContext(ConfigInterface $config): self
    {
        if ($config->has(DiFactoryInterface::NODE_DEPENDS)) {
            $modules = $config
                ->get(DiFactoryInterface::NODE_DEPENDS);
            
            if (!is_array($modules)) {
                $modules = [$modules];
            }

            foreach ($modules as $module) {
                $modulePath = $this->getConfig()
                    ->createPath(DiFactoryInterface::NODE_DEPENDENCY, $module);

                if (!$this->getConfig()->has($modulePath)) {
                    throw new LogicException(
                        sprintf(_('Module path not found "%s"'), $modulePath)
                    );
                }

                $moduleConfig = $this->getConfig()
                    ->fromPath($modulePath);
                
                $this->applyDependencyContext($moduleConfig);

                $this->getConfig()
                    ->merge($moduleConfig->asArray());
            }

            $config->unset(DiFactoryInterface::NODE_DEPENDS);
        }
       
        return $this;
    }

    protected function applyReferenceContext(ConfigInterface $config): self
    {
        if ($config->has(DiFactoryInterface::NODE_REFERENCE)) {
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

        return $this;
    }

    protected function applySubDiContext(ConfigInterface $config): self
    {
        if ($config->has(DiFactoryInterface::NODE_DI_CONFIG)) {
            $this->getConfig()
                ->merge(
                    $config->get(DiFactoryInterface::NODE_DI_CONFIG)
                );
        }

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

    protected function addServiceStack(string $serviceId): self
    {
        $this->serviceStack[$serviceId] = true;

        return $this;
    }

    protected function isInServiceStack(string $serviceId): bool
    {
        return array_key_exists($serviceId, $this->serviceStack);
    }

    protected function removeServiceStack(string $serviceId): self
    {
        unset($this->serviceStack[$serviceId]);

        return $this;
    }
}