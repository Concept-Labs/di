<?php
namespace Concept\Di\Factory\Composer;

use Concept\Config\ConfigInterface;

interface PackageConfigInterface extends ConfigInterface
{

    /**
     * Load the composer package
     * 
     * @param string $filename
     * 
     * @return static
     */
    public function loadPackage(string $filename): static;

    /**
     * Get the composer data
     * 
     * @return array
     */
    public function getComposerData(): array;

    /**
     * Get the package name
     * 
     * @return string
     */
    public function getPackageName(): string;


    public function isCompatible(): bool;
    public function setCompabilityValidator(callable $validator): static;
}