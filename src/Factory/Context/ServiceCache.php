<?php
/**
 * ServiceCache.php
 *
 * This file is part of the Concept Labs Dependency Injection package.
 *
 * @package     Concept\Di
 * @category    DependencyInjection
 * @author      Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license     https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 * @link        https://github.com/concept-labs/di
 */

namespace Concept\Di\Factory\Context;

use ReflectionClass;
use Concept\Di\Factory\Exception\RuntimeException;
use Concept\PathAccess\PathAccessInterface;


class ServiceCache  implements ServiceCacheInterface
{
    /**
     * Service instance
     *
     * @var object
     */
    private ?object $serviceInstance;
    /**
     * Service ID
     *
     * @var string
     */
    private ?string $serviceId = null;
    /**
     * Service config context
     *
     * @var PathAccessInterface
     */
    private ?PathAccessInterface $serviceConfig = null;
    /**
     * Service reflection
     *
     * @var ReflectionClass
     */
    private ?ReflectionClass $serviceReflection = null;
    /**
     * Service arguments
     *
     * @var array
     */
    private array $serviceArguments = [];

    /**
     * {@inheritDoc}
     */
    public function reset(): self
    {
        $this->serviceInstance = null;
        $this->serviceId = null;
        $this->serviceConfig = null;
        $this->serviceReflection = null;
        $this->serviceArguments = [];

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstance(): ?object
    {
        return $this->serviceInstance;
    }

    /**
     * {@inheritDoc}
     */
    public function setInstance($instance): self
    {
        $this->serviceInstance = $instance;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getServiceId(): string
    {
        if ($this->serviceId === null) {
            throw new RuntimeException('Service ID not set');
        }
        return $this->serviceId;
    }

    /**
     * {@inheritDoc}
     */
    public function setServiceId(string $serviceId): self
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigContext(): ?PathAccessInterface
    {
        return $this->serviceConfig;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfigContext(PathAccessInterface $serviceConfig): self
    {
        $this->serviceConfig = $serviceConfig;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getReflection(): ?ReflectionClass
    {
        return $this->serviceReflection;
    }

    /**
     * {@inheritDoc}
     */
    public function setReflection(ReflectionClass $serviceReflection): self
    {
        $this->serviceReflection = $serviceReflection;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getArguments(): array
    {
        return $this->serviceArguments;
    }

    /**
     * {@inheritDoc}
     */
    public function setArguments($serviceArguments): self
    {
        $this->serviceArguments = $serviceArguments;

        return $this;
    }
}
