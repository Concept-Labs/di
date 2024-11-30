<?php
/**
 * ServiceFactoryInterface
 *
 * This interface defines the contract for service factories within the Concept framework.
 *
 * @package     Concept\Di
 * @category    DependencyInjection
 * @author      Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license     https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 * @link        https://github.com/concept-labs/di
 */
namespace Concept\Di\Factory\Service;

use Concept\Factory\FactoryInterface;

abstract class ServiceFactory implements ServiceFactoryInterface
{
    /**
     * Create a new instance of the service
     * 
     * @param mixed ...$args
     * @return mixed
     */
    abstract public function create(...$args);

    /**
     * The factory
     * 
     * @var FactoryInterface
     */
    private ?FactoryInterface $factory = null;

    public function __construct(FactoryInterface $factory)
    {
        /**
         * Freeze the factory context
         * @see DiFactory::__clone()
         */
        $this->factory = clone $factory;

        return $this;
    }

    /**
     * Get the factory
     * 
     * @return FactoryInterface
     */
    protected function getFactory(): FactoryInterface
    {
        return $this->factory;
    }
}