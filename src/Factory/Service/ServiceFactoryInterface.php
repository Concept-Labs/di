<?php
namespace Concept\Di\Factory\Service;

interface ServiceFactoryInterface
{
    /**
     * @param mixed ...$args
     * 
     * @return mixed
     */
    public function create(...$args);
}