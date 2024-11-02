<?php

namespace Concept\Di\Factory;

use Psr\Container\ContainerInterface;
use Concept\Config\ConfigInterface;
use Concept\Factory\FactoryInterface;

interface DiFactoryInterface extends FactoryInterface
{
    const NODE_DI_CONFIG = 'di';
    const NODE_NAMESPACE = 'namespace';
    const NODE_MODULE = 'module';
    const NODE_DEPENDS = 'depends';
    const NODE_PREFERENCE = 'preference';
    const NODE_REFERENCE = 'reference';
    const NODE_CLASS = 'class';
    const NODE_SINGLETON = 'singleton';
    const NODE_PARAMETERS = 'parameters';
    const NODE_PARAMETER_VALUE = 'value';

    const INLINE_DI_CONFIG_CONSTANT = 'DI_CONFIG_INLINE';
    const DYNAMIC_DI_CONFIG_METHOD = '___config';
    const DI_METHOD_PREFIX = '___di';
    const DI_ATTRIBUTE = 'DI';
    //const DI_METHOD = '___di';

    /**
     * Get the factory clone and set the container
     * 
     * @param ContainerInterface $container
     * 
     * @return mixed
     */
    public function withContainer(ContainerInterface $container): self;

    /**
     * Get the factory clone and set the config
     * 
     * @param ConfigInterface $config
     * 
     * @return mixed
     */
    public function withConfig(ConfigInterface $config): self;

    /**
     * Reset the factory
     */
    public function reset();


}