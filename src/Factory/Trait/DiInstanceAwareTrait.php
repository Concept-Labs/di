<?php
namespace ConceptLabs\Di\Factory\Trait;

trait DiInstanceAwareTrait
{
    /**
     * DI instances.
     * 
     * @var array<string, object>
     */
    protected array $___diInstances = [];

    /**
     * Get a DI instance.
     * 
     * @param string $service The service id
     * 
     * @return mixed
     */
    protected function getDiInstance(string $serviceId)
    {
        if (!isset($this->___diInstances[$serviceId])) {
            throw new \RuntimeException(sprintf('DI instance for service "%s" not set', $serviceId));
        }

        return $this->___diInstances[$serviceId];
    }
}