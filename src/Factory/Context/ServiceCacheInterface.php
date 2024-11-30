<?php
/**
 * Interface ServiceCacheInterface
 * 
 * This interface defines the contract for a service cache within the dependency injection framework.
 * Implementations of this interface are responsible for caching service instances to improve performance
 * by avoiding redundant service creation.
 * 
 * @package     Concept\Di
 * @category    DependencyInjection
 * @author      Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license     https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 * @link        https://github.com/concept-labs/di
 */
 
namespace Concept\Di\Factory\Context;

use ReflectionClass;
use Concept\PathAccess\PathAccessInterface;

interface ServiceCacheInterface
{
    /**
     * Reset service cache
     * 
     * @return self
     */
    public function reset(): self;

    /**
     * Get service instance
     * 
     * @return object
     */
    public function getInstance(): ?object;

    /**
     * Set service instance
     * 
     * @param object $service
     * 
     * @return self
     */
    public function setInstance(object $service): self;

    /**
     * Get service ID
     * 
     * @return string
     */
    public function getServiceId(): string;

    /**
     * Get service Id
     * 
     * @return self
     */
    public function setServiceId(string $serviceId): self;

    /**
     * Set service ID
     * 
     * @param string $serviceId
     * 
     * @return self
     */
    public function getConfigContext(): ?PathAccessInterface;

    /**
     * Set service config context
     * 
     * @param PathAccessInterface $serviceConfig
     * 
     * @return self
     */
    public function setConfigContext(PathAccessInterface $serviceConfig): self;

    /**
     * Get service reflection
     * 
     * @return ReflectionClass
     */
    public function getReflection(): ?ReflectionClass;

    /**
     * Set service reflection
     * 
     * @param ReflectionClass $serviceReflection
     * 
     * @return self
     */
    public function setReflection(ReflectionClass $serviceReflection): self;

    /**
     * Get service arguments
     * 
     * @return array
     */
    public function getArguments(): array;

    /**
     * Set service arguments
     * 
     * @param array $serviceArguments
     * 
     * @return self
     */
    public function setArguments(array $serviceArguments): self;
}