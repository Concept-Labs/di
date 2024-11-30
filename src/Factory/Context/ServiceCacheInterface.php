<?php
namespace Concept\Di\Factory\Context;

use ReflectionClass;

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
    public function getServiceInstance(): object;

    public function setServiceInstance(object $service): self;

    public function getServiceId(): string;

    public function setServiceId(string $serviceId): self;

    // public function getServiceClass(): string;

    // public function setServiceClass(string $serviceClass): self;

    public function getServiceReflection(): ?ReflectionClass;

    public function setServiceReflection(ReflectionClass $serviceReflection): self;

    public function getServiceArguments(): array;

    public function setServiceArguments(array $serviceArguments): self;
}