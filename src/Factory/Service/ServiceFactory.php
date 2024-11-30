<?php
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