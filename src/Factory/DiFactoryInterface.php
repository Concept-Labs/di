<?php
/**
 * DiFactoryInterface.php
 *
 * This file contains the interface definition for the DiFactoryInterface.
 *
 * @package     Concept\Di
 * @category    DependencyInjection
 * @author      Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license     https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 * @link        https://github.com/concept-labs/di
 */
namespace Concept\Di\Factory;

use Psr\Container\ContainerInterface;
use Concept\Di\Factory\Context\ConfigContextInterface;
use Concept\Factory\FactoryInterface;

interface DiFactoryInterface extends FactoryInterface
{

    public function create(?string $serviceId = null, ...$args);//: object;

     /**
     * Create a lazy service
     * 
     * @param string $serviceId
     * @param mixed ...$args
     * 
     * @return callable
     */
    public function lazyCreate(string $serviceId, ...$args): callable;

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
     * @param ConfigContextInterface $config
     * 
     * @return mixed
     */
    public function withConfigContext(ConfigContextInterface $config): self;

}