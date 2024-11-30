<?php

namespace Concept\Di\Factory\Context;

use Concept\Di\Factory\Exception\RuntimeException;
use ReflectionClass;

class ServiceCache  implements ServiceCacheInterface
{

    private ?string $serviceId = null;
    //private ?string $serviceClass = null;
    private ?ReflectionClass $serviceReflection = null;
    private array $serviceArguments = [];
    private ?object $serviceInstance;

    public function reset(): self
    {
        $this->serviceInstance = null;
        $this->serviceId = null;
        //$this->serviceClass = null;
        $this->serviceReflection = null;
        $this->serviceArguments = [];

        return $this;
    }

    public function getServiceInstance(): object
    {
        return $this->serviceInstance;
    }

    public function setServiceInstance($instance): self
    {
        $this->serviceInstance = $instance;

        return $this;
    }

    public function getServiceId(): string
    {
        if ($this->serviceId === null) {
            throw new RuntimeException('Service ID not set');
        }
        return $this->serviceId;
    }

    public function setServiceId(string $serviceId): self
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    // public function getServiceClass(): string
    // {
    //     if ($this->serviceClass === null) {
    //         throw new ServiceClassNotResolvedException('Service class not set');
    //     }

    //     return $this->serviceClass;
    // }

    // public function setServiceClass($serviceClass): self
    // {
    //     $this->serviceClass = $serviceClass;

    //     return $this;
    // }

    public function getServiceReflection(): ?ReflectionClass
    {


        return $this->serviceReflection;
    }

    public function setServiceReflection(ReflectionClass $serviceReflection): self
    {
        $this->serviceReflection = $serviceReflection;

        return $this;
    }

    public function getServiceArguments(): array
    {
        return $this->serviceArguments;
    }

    public function setServiceArguments($serviceArguments): self
    {
        $this->serviceArguments = $serviceArguments;

        return $this;
    }
}
