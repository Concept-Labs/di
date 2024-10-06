<?php

namespace Concept\Di\Factory;

use Psr\Container\ContainerInterface;
use Concept\Config\ConfigInterface;
use Concept\Factory\FactoryInterface;

interface DiFactoryInterface extends FactoryInterface
{
    const NODE_DI_CONFIG = 'di';
    //const NODE_MODULE = 'module';
    const NODE_DEPENDENCY = 'dependency';
    const NODE_DEPENDS = 'depends';
    const NODE_NAMESPACE = 'namespace';
    const NODE_PREFERENCE = 'preference';
    const NODE_CLASS = 'class';
    const NODE_REFERENCE = 'reference';
    const NODE_SINGLETON = 'singleton';
    const NODE_PARAMETERS = 'parameters';
    const NODE_PARAMETER_VALUE = 'value';

    const INLINE_DI_CONFIG_CONSTANT = 'DI_CONFIG_INLINE';
    const INLINE_DI_CONFIG_FILE_CONSTANT = 'DI_CONFIG_FILE';
    const DYNAMIC_DI_CONFIG_METHOD = '___diConfig';
    const DI_METHOD = '___di';


    public function withContainer(ContainerInterface $container): self;
    public function withConfig(ConfigInterface $config): self;

}