<?php
/**
 * Interface ConfigContextInterface
 *
 * This interface defines the contract for configuration context within the dependency injection system.
 * Implementations of this interface are responsible for providing configuration settings required by the DI factory.
 *
 * @package Concept\Di
 * @category DependencyInjection
 * @author Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 */
namespace Concept\Di\Factory\Context;

use Concept\Config\ConfigInterface;
use Concept\DI\Factory\Attribute\Injector;
use Concept\PathAccess\PathAccessInterface;

interface ConfigContextInterface extends ConfigInterface
{
    const NODE_DI_CONFIG = 'di';
    const NODE_NAMESPACE = 'namespace';
    const NODE_PACKAGE = 'package';
    const NODE_DEPENDS = 'depends';
    const NODE_PREFERENCE = 'preference';
    const NODE_REFERENCE = 'reference';
    const NODE_CLASS = 'class';
    const NODE_CONFIG = 'config';
    const NODE_SINGLETON = 'singleton';
    const NODE_PARAMETERS = 'parameters';
    const NODE_PARAMETER_VALUE = 'value';

    /**
     * @todo: deprecate such usage
     * @todo: use Attributes instead (PHP >= 8.0)
     */
    const DI_METHOD_PREFIX = '___di';
    const DI_ATTRIBUTE = Injector::class;

    // const INLINE_DI_CONFIG_CONSTANT = 'DI_CONFIG_INLINE';
    // const DYNAMIC_DI_CONFIG_METHOD = '___config';
    //const DI_METHOD = '___di';
   
    /**
     * Build service config context
     * 
     * @param string $serviceId
     * @param array $config
     * 
     * @return static
     * 
     * @throws LogicException
     */
    public function buildServiceContext($serviceId, array $config = []): static;

    /**
     * Get service config after building the context
     * 
     * @param string $serviceId
     * 
     * @return PathAccessInterface
     */
    public function getServiceConfig(string $serviceId): PathAccessInterface;
}