<?php
namespace Concept\Di\Factory;


use ReflectionMethod;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Concept\Config\Config;
use Concept\Config\ConfigInterface;
use Concept\Di\Factory\Exception\InvalidArgumentException;
use Concept\Di\Factory\Exception\LogicException;

class DiFactory implements DiFactoryInterface
{
    protected ?ConfigInterface $config = null;
    
    protected ?ContainerInterface $container = null;
    protected ?string $serviceId = null;
    protected ?array $parameters = null;

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

    public function withServiceId(string $serviceId): self
    {
        $clone = clone $this;
        $clone->serviceId = $serviceId;

        return $clone;
    }

    public function withParameters(...$parameters): self
    {
        $clone = clone $this;
        $clone->parameters = $parameters;

        return $clone;
    }

    protected function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    protected function getConfig(): ConfigInterface
    {
        return $this->config ?? new Config();
    }

    protected function getServiceId(): string
    {
        return $this->serviceId;
    }

    protected function getParameters(): ?array
    {
        return $this->parameters;
    }

    protected function assertState(): void
    {
        // if (null === $this->container) {
        //     throw new InvalidArgumentException('Container is not set');
        // }

        // if (null === $this->config) {
        //     throw new InvalidArgumentException('Config is not set');
        // }

        if (null === $this->serviceId) {
            throw new InvalidArgumentException('Service ID is not set');
        }
    }

    public function create(...$parameters): object
    {
        /**
         * @todo avoid recursion
         */
        if (!empty($parameters)) {
            $this->parameters = $parameters;
        }

        $this->assertState();

        $service = $this->createService();

        return $service;
        
        //$service = $this->getServiceFromContainer() ?? 
    }

    protected function createService()
    {
        $this->prepareConfigState();
        
        $preferenceConfig = $this->getPreferenceConfig();

        $serviceClass = $preferenceConfig->get(DiFactoryInterface::NODE_CLASS);

        $reflection = new ReflectionClass($serviceClass);

        $this->applyReflectionConfig($reflection);

        if (!$reflection->isInstantiable()) {
            throw new LogicException(
                sprintf(_('Class "%s" is not instantiable'), $serviceClass)
            );
        }

        $constructor = $reflection->getConstructor();
        $parameters = [];

        if (!$constructor instanceof ReflectionMethod) {
            /**
             * No constructor found, just return a new instance
             */
            $service = $reflection->newInstanceWithoutConstructor();
        } else {
            $parameters = $this->resolveParameters($constructor);
            $service = $reflection->newInstance(...$parameters);
        }

        /**
         * DI method call
         * 
         */
        if (method_exists($service, DiFactoryInterface::DI_METHOD)) {
            $reflectionMethod = $reflection->getMethod(DiFactoryInterface::DI_METHOD);
            $parameters = $this->resolveParameters($reflectionMethod);
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($service, ...$parameters);
        }

        $this->applyServiceLifeCycle($service);

        $this->restoreConfigState();

        return $service;
    }

    protected function prepareConfigState(): self
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

    protected function applyReflectionConfig(ReflectionClass $reflection): self
    {
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

    protected function getPreferenceConfig(): ConfigInterface
    {
        $preferencePath = $this->getConfig()
            ->createPath(DiFactoryInterface::NODE_PREFERENCE, $this->getServiceId());

        return $this->getConfig()
            ->fromPath($preferencePath);
    }

    protected function getPreferenceParametersConfig(): ConfigInterface
    {
        $preferenceConfig = $this->getPreferenceConfig();

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
        $parametersConfig = $this->getPreferenceParametersConfig();

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
            
            $parameters[] = $this->getContainer()->has($typeName) 
                ? $this->getContainer()->get($typeName) 
                : $this->withServiceId($typeName)->create();
        }

        return $parameters;
    }

    protected function applyServiceLifeCycle(object $service): self
    {
        $preferenceConfig = $this->getPreferenceConfig();

        if ($preferenceConfig->get(DiFactoryInterface::NODE_SINGLETON)) {
            if(null !== $container = $this->getContainer()) {
                if (method_exists($container, 'attach')) {
                    /**
                     * @todo container interface?
                     */
                    $container->attach($this->getServiceId(), $service);
                }
            }
        }

        return $this;
    }

    protected function restoreConfigState() 
    {
        $this->getConfig()
            ->popState();
    }
}