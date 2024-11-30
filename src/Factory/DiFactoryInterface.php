<?php

namespace Concept\Di\Factory;

use Psr\Container\ContainerInterface;
use Concept\Di\Factory\Context\ConfigContextInterface;
use Concept\Factory\FactoryInterface;

interface DiFactoryInterface extends FactoryInterface
{

    public function create(?string $serviceId = null, ...$args);//: object;

    /**
     * Get the factory clone and set the container
     * 
     * @param ContainerInterface $container
     * 
     * @return mixed
     */
    public function withContainer(ContainerInterface $container): self;

    public function setContainer(ContainerInterface $container): self;

    /**
     * Get the factory clone and set the config
     * 
     * @param ConfigContextInterface $config
     * 
     * @return mixed
     */
    public function withConfigContext(ConfigContextInterface $config): self;

}