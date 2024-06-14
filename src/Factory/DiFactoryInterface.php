<?php

namespace Concept\Di\Factory;

use Psr\Container\ContainerInterface;
use Concept\Config\ConfigInterface;

interface DiFactoryInterface extends FactoryInterface
{
    const NODE_PREFERENCE = 'preference';
    const NODE_DI_CONFIG = 'di';
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

    public function withServiceId(string $serviceId): self;
    public function withParameters(...$parameters): self;
}