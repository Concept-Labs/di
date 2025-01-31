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

use Concept\Config\ConfigInterface;
use ReflectionClass;
use Concept\PathAccess\PathAccessInterface;

interface ServiceCacheInterface
{
    /**
     * Reset service cache
     * 
     * @return static
     */
    public function reset(): static;

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
     * @return static
     */
    public function setInstance(object $service): static;

    /**
     * Get service ID
     * 
     * @return string
     */
    public function getServiceId(): string;

    /**
     * Get service Id
     * 
     * @return static
     */
    public function setServiceId(string $serviceId): static;

    /**
     * Set service ID
     * 
     * @param string $serviceId
     * 
     * @return ConfigInterface
     */
    public function getConfigContext(): ?ConfigInterface;

    /**
     * Set service config context
     * 
     * @param PathAccessInterface $serviceConfig
     * 
     * @return static
     */
    public function setConfigContext(ConfigInterface $serviceConfig): static;

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
     * @return static
     */
    public function setReflection(ReflectionClass $serviceReflection): static;

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
     * @return static
     */
    public function setArguments(array $serviceArguments): static;
}