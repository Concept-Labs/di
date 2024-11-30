<?php
namespace Concept\Di\Factory\Context;

use Concept\Config\ConfigInterface;

interface ConfigContextInterface extends ConfigInterface
{
    const NODE_DI_CONFIG = 'di';
    const NODE_NAMESPACE = 'namespace';
    const NODE_PACKAGE = 'package';
    const NODE_DEPENDS = 'depends';
    const NODE_PREFERENCE = 'preference';
    const NODE_REFERENCE = 'reference';
    const NODE_CLASS = 'class';
    const NODE_SINGLETON = 'singleton';
    const NODE_PARAMETERS = 'parameters';
    const NODE_PARAMETER_VALUE = 'value';

    /**
     * @todo: deprecate such usage
     * @todo: use Attributes instead (PHP >= 8.0)
     */
    const DI_METHOD_PREFIX = '___di';
    const DI_ATTRIBUTE = 'ConceptDI';

    // const INLINE_DI_CONFIG_CONSTANT = 'DI_CONFIG_INLINE';
    // const DYNAMIC_DI_CONFIG_METHOD = '___config';
    //const DI_METHOD = '___di';
   
    /**
     * Build service config context
     * 
     * @param string $serviceId
     * @param array $config
     * 
     * @return self
     * 
     * @throws LogicException
     */
    public function buildServiceContext($serviceId, array $config = []): self;
}