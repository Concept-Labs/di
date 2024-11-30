<?php
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