<?php
/**
/**
 * DiFactory class
 *
 * This class is responsible for creating and managing dependency injection instances.
 * It provides methods to register and retrieve services, ensuring that dependencies
 * are properly injected and managed throughout the application.
 *
 * @package     Concept\Di
 * @category    DependencyInjection
 * @author      Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license     https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 * @link        https://github.com/concept-labs/di
 */
namespace Concept\Di\Factory;

use ReflectionMethod;
use ReflectionClass;
use ReflectionNamedType;
use Concept\Di\Factory\Context\ConfigContext;
use Psr\Container\ContainerInterface;
use Concept\Di\Factory\Context\ConfigContextInterface;
use Concept\Di\Factory\Context\ServiceCache;
use Concept\Di\Factory\Context\ServiceCacheInterface;
use Concept\Di\Factory\Exception\DiFactoryException;
use Concept\Di\Factory\Exception\LogicException;
use Concept\Di\Factory\Exception\RuntimeException;
use Concept\PathAccess\PathAccessInterface;

class DiFactory implements DiFactoryInterface
{

    private ?ContainerInterface $container = null;
    private ?ServiceCacheInterface $serviceCache = null;

    private ?ConfigContextInterface $configRuntimeContext = null;
    private ?ConfigContextInterface $overrides = null;

    private array $serviceStack = [];


    public function __construct()
    {
        $this->serviceCache = new ServiceCache();
    }
    
    /**
     * Clone the factory 
     * Original factory must be immutable to keep working with the same state
     * Dependencies must be resolved by cloned factory with already built state and change the state during the resolution
     * 
     * @return void
     */
    public function __clone()
    {
        $this->serviceCache = clone $this->serviceCache;

        if (null !== $this->configRuntimeContext) {
            $this->configRuntimeContext = clone $this->configRuntimeContext;
        }
    }

    public function __destruct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function withConfigContext(ConfigContextInterface $configContext): self
    {
        $clone = clone $this;
        $clone->configRuntimeContext = $configContext;
        
        if ($configContext->has(ConfigContextInterface::NODE_PREFERENCE)) {
            $clone->overrides = $configContext->from(ConfigContextInterface::NODE_PREFERENCE);
        }

        return $clone;
    }

    /**
     * Get the runtime context
     * 
     * @return ConfigContextInterface|null
     */
    protected function getRuntimeContext(): ConfigContextInterface
    {
        return $this->configRuntimeContext ?? new ConfigContext();
    }

    /**
     * Get the service cache
     * 
     * @return ServiceCacheInterface
     */
    protected function getServiceCache(): ServiceCacheInterface
    {
        return $this->serviceCache;
    }

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;

        return $this;
    }
    
    /**
     * {@inheritDoc}
     */
    public function withContainer(ContainerInterface $container): self
    {
        return $this->setContainer($container);

        /**
         * Do not clone the factory
         * @todo: think about it
         */
        // $clone = clone $this;
        // $clone->container = $container;

        // return $clone;
    }

    /**
     * Get the container
     * 
     * @return ContainerInterface
     */
    protected function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the service ID
     * 
     * @return string
     */
    protected function getServiceId(): string
    {
        $serviceId = $this->getServiceCache()->getServiceId();
        
        if (null === $serviceId) {
            throw new RuntimeException('Service ID is not set');
        }
        return $serviceId;
    }

    /**
     * Get the service instance
     * 
     * @return mixed
     */
    protected function getServiceInstance()
    {
        $service = $this->getServiceCache()->getInstance();

        if (null === $service) {
            throw new LogicException('Service is not created yet');
        }

        return $service;
    }

    /**
     * Create service instance
     * 
     * @param string|null $serviceId
     * @param mixed ...$args
     * 
     * @return object
     */
    public function create(?string $serviceId = null, ...$args): object
    {
        if (null === $serviceId) {
            throw new DiFactoryException('Service ID is not passed');
        }

        $this->initServiceCache($serviceId, $args);

        /**
         * If the service is registered in the container, return it
         */
        if ($this->getContainer() && $this->getContainer()->has($serviceId)) {
            return $this->getContainer()->get($serviceId);
        }

        $this
            /**
             * Assert dependency stack
             * Avoid circular dependencies
             */
            ->assertDependencyStack()->addServiceStack($this->getServiceId())
            /**
             * Buildruntime config context
             */
            ->buildConfigContext($this->getServiceId())
            /**
             * CREATE SERVICE INSTANCE
             */
            ->instantiate()
            /**
             * Invoke DI methods
             */
            ->invokeDi()
            /**
             * Invoke DI methods using attributes
             */
            ->invokeDiAttribute()
            /**
             * Apply service life cycle
             */
            ->applyServiceLifeCycle()
            /**
             * Remove service id from the circular dependency stack
             */
            ->removeServiceStack($this->getServiceId());

        return $this->getServiceInstance();
    }

    /**
     * Create a lazy service
     * 
     * @param string $serviceId
     * @param mixed ...$args
     * 
     * @return callable
     */
    public function lazyCreate(string $serviceId, ...$args): callable
    {
        return function () use ($serviceId, $args) {
            return $this->create($serviceId, ...$args);
        };
    }

    /**
     * Assert factory state
     * Check if the service is not in the service stack
     * 
     * @return self
     */
    protected function assertDependencyStack(): self
    {
        if ($this->isInServiceStack($this->getServiceId())) {
            throw new LogicException(
                sprintf(_('Circular dependency detected for service "%s"'), $this->getServiceId())
            );
        }

        return $this;
    }

    /**
     * Initialize the service cache
     * 
     * @param string|null $serviceId
     * @param array $args
     * 
     * @return self
     */
    protected function initServiceCache(?string $serviceId = null, array $args = []): self
    {
        $this->getServiceCache()->reset()
            ->setServiceId($serviceId)
            ->setArguments($args);

        return $this;
    }

    /**
     * @return self
     */
    protected function buildConfigContext(string $serviceId/*, array $config = []*/): self
    {
        $this->getRuntimeContext()
            ->buildServiceContext($serviceId)
            /**
             * use Initial top levels with top priority
             * + use bubbled preferences as overrides
             * @todo: think about it, performance
             */
            ->merge([ConfigContextInterface::NODE_PREFERENCE => $this->overrides ? $this->overrides->asArray() : []]);
            ;
        

        return $this;
    }
        
    /**
     * Create service instance
     * Use reflection class to create the instance
     * If the class is instantiable without constructor, create the instance
     * If the class has a constructor, resolve the parameters
     * If the class is not instantiable, throw an exception
     * 
     * @return self
     */
    protected function instantiate(): self
    {
        $serviceInstance = null;
        $reflection = $this->getServiceReflection();

        if (!$reflection->isInstantiable()) {
            throw new LogicException(
                sprintf(
                    _('Service "%s" (resolved preference: "%s") is not instantiable. Check Dependency Configuration'), 
                    $this->getServiceId(),
                    $reflection->getName()
                )
            );
        }

        if (!$reflection->getConstructor() instanceof ReflectionMethod) {
            /**
             * No constructor
             */
            $serviceInstance = $reflection->newInstanceWithoutConstructor();
        } else {
            /**
             * Constructor.
             * Resolve parameters
             */
            $parameters = $this->resolveParameters($reflection->getConstructor());

            $serviceInstance = $reflection->newInstance(...$parameters);
        }

        

        if (!is_object($serviceInstance)) {
            throw new LogicException(
                sprintf(
                    _('Unable to create dependency: Service "%s" (resolved preference: "%s") is not an object'), 
                    $this->getServiceId(),
                    $reflection->getName()
                )
            );
        }

        /**
         * strict check ?
         * Check if the service instance is an instance of the service ID
         */
        // if (!is_subclass_of($serviceInstance, $this->getServiceId())) {
        //     throw new LogicException(
        //         sprintf(
        //             _('Unable to create dependency: Service "%s" (resolved preference: "%s") is not an instance of "%s"'), 
        //             $this->getServiceId(),
        //             $reflection->getName(),
        //             $this->getServiceId()
        //         )
        //     );
        // }

        $this->getServiceCache()->setInstance($serviceInstance);

        return $this;
    }

    /**
     * Invoke DI methods
     * Methods with the prefix ConfigContextInterface::DI_METHOD_PREFIX are invoked
     * Resolve parameters for each method
     * The method is invoked with resolved parameters
     * 
     * @return self
     */
    protected function invokeDi(): self
    {
        $reflection = $this->getServiceReflection();

        foreach ($reflection->getMethods() as $method) {
            //@todo: uncomment to improve performance get only private and final at same time  methods
            //@todo: deprecated this method and move logic to attributes php 8.0
            //foreach ($reflection->getMethods(ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_FINAL ) as $method) {
            if (strpos($method->getName(), ConfigContextInterface::DI_METHOD_PREFIX) === 0) {
                $parameters = $this->resolveParameters($method);
                $method->setAccessible(true);
                $method->invoke($this->getServiceInstance(), ...$parameters);
            }
        }

        return $this;
    }

    /**
     * Invoke DI methods using attributes
     * Methods with the ConfigContextInterface::DI_ATTRIBUTE attribute are invoked
     * Resolve parameters for each method
     * The method is invoked with resolved parameters
     * 
     * @return self
     */
    protected function invokeDiAttribute(): self
    {
        $reflection = $this->getServiceReflection();
        if (!method_exists($reflection, 'getAttributes')) {
            // PHP < 8.0
            return $this;
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(ConfigContextInterface::DI_ATTRIBUTE)) {
                $parameters = $this->resolveParameters($method);
                $method->setAccessible(true);
                $method->invoke(
                    $this->getServiceInstance(),
                    ...$parameters
                );
            }
        }

        return $this;
    }

    /**
     * Apply service life cycle
     * If the service is a singleton, attach it to the container
     * 
     * @return self
     */
    protected function applyServiceLifeCycle(): self
    {
        $preferenceConfig = $this->getServiceConfigContext($this->getServiceId());

        if ($preferenceConfig->get(ConfigContextInterface::NODE_SINGLETON)) {
            if(null !== $container = $this->getContainer()) {
                if ($container instanceof \Concept\Container\ContainerInterface) {
                    $container->attach(
                        $this->getServiceId(),
                        $this->getServiceInstance()
                    );
                }
            }
        }

        return $this;
    }

    /**
     * Get the service reflection
     * 
     * @return ReflectionClass
     */
    protected function getServiceReflection(): ReflectionClass
    {
        $reflection = $this->getServiceCache()->getReflection();

        if (null === $reflection) {

            $resolvedClass = $this->getServiceConfigContext()->get(ConfigContextInterface::NODE_CLASS);

            try {
                $reflection = new ReflectionClass($resolvedClass);
            } catch (\ReflectionException $e) {
                throw new LogicException(
                    sprintf(
                        _('Unable to create dependency: Service "%s" (resolved preference: "%s") not found'),
                        $this->getServiceId(),
                        $resolvedClass
                        )
                );
            }

            $this->getServiceCache()->setReflection($reflection);
        }

        return $reflection;
    }

    /**
     * Get the service config context
     * must be built before
     * 
     * @return PathAccessInterface
     */
    protected function getServiceConfigContext(): PathAccessInterface
    {
        $serviceConfig = $this->getServiceCache()->getConfigContext();

        if (null === $serviceConfig) {
            
            $serviceConfig = $this->getRuntimeContext()
                ->getServiceConfig($this->getServiceId());

            $this->getServiceCache()->setConfigContext($serviceConfig);
        }

        return $serviceConfig;
    }

    /**
     * Resolve parameters
     * 
     * @param ReflectionMethod $method
     * 
     * @return array
     */
    protected function resolveParameters(ReflectionMethod $method): array
    {
        $passedAruments = $this->getServiceCache()->getArguments();
        if (!empty($passedAruments)) {
            /**
             * Parameters are passed explicitly
             */
            return $passedAruments;
        }

        $methodParameters = $method->getParameters();
        if (empty($methodParameters)) {
            /**
             * No parameters need to be resolved
             */
            return [];
        }

        /**
         * Service parameters configuration
         */
        $serviceConfig = $this->getServiceConfigContext($this->getServiceId());

        $parametersConfig = $serviceConfig->has(ConfigContextInterface::NODE_PARAMETERS)
            ? $serviceConfig->from(ConfigContextInterface::NODE_PARAMETERS)
            : $serviceConfig->withData([]);

        $dependencies = [];
        foreach ($methodParameters as $parameter) {
            if ($parametersConfig->has($parameter->getName())) {
                /**
                 * Only for static values.
                 * To set custom prefrence configuration add sub "di" node
                 * "preference": {
                 *    "serviceId": {
                 *      "di": {
                 *        ...
                 *          "preference": {
                 *              ...
                 *          }
                 *    }
                 * }
                 */
                $dependencies[] = $parametersConfig->get($parameter->getName(), ConfigContextInterface::NODE_PARAMETER_VALUE);
                continue;
            }

            if ($parameter->isOptional()) {
                /**
                 * Parameter is optional. Use the default value
                 */
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->isVariadic()) {
                throw new LogicException(
                    sprintf(
                        _('Unable to create dependency: Variadic parameters are not supported. Method: %s'),
                        $method->getName()
                    )
                );
            }

            $parameterType = $parameter->getType();
            if (!$parameterType instanceof ReflectionNamedType) {
                throw new LogicException(
                    sprintf(_('Unable to create dependency: Parameter "%s" has no named type. Currently is not supported. Method: %s'), $parameter->getName(), $method->getName())
                );
            }

            $dependencyServiceId = $parameterType->getName();

            if (empty($dependencyServiceId)) {
                throw new LogicException(
                    sprintf(_('Unable create dependency: Parameter "%s" is not typed'), $parameter->getName())
                );
            }

            $dependencies[] = $this->getContainer()->has($dependencyServiceId) 
                ? $this->getContainer()->get($dependencyServiceId) 
                /**
                 * Important to clone the factory for the new service to it has 
                 * the same config context
                 */
                : (clone $this)->create($dependencyServiceId);
        }

        return $dependencies;
    }

    /**
     * Add service id to the service stack
     * 
     * @param string $serviceId
     * 
     * @return self
     */
    protected function addServiceStack(string $serviceId): self
    {
        $this->serviceStack[$serviceId] = true;

        return $this;
    }

    /**
     * Check if the service id is in the service stack
     * 
     * @param string $serviceId
     * 
     * @return bool
     */
    protected function isInServiceStack(string $serviceId): bool
    {
        return array_key_exists($serviceId, $this->serviceStack);
    }

    /**
     * Remove service id from the service stack
     * 
     * @param string $serviceId
     * 
     * @return self
     */
    protected function removeServiceStack(string $serviceId): self
    {
        unset($this->serviceStack[$serviceId]);

        return $this;
    }

}