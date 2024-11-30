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

interface ServiceFactoryInterface
{
    /**
     * @param mixed ...$args
     * 
     * @return mixed
     */
    public function create(...$args);
}