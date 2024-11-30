<?php
/**
 * ConfigContext
 *
 * This file is part of the Concept Labs Dependency Injection package.
 * It is responsible for managing the configuration context within the DI framework.
 *
 * @package     Concept\Di
 * @category    DependencyInjection
 * @author      Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license     https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 * @link        https://github.com/concept-labs/di
 */
namespace Concept\Di\Factory\Composer;

use Concept\Config\ConfigInterface;

interface ComposerContextInterface extends ConfigInterface
{
    /**
     * Build composer context
     * 
     * @return self
     */
    public function buildComposerContext(): self;
}