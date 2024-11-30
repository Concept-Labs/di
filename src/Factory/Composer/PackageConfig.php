<?php
/**
 * PackageConfig
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

use Concept\Config\Config;
use Concept\Di\Factory\Context\ConfigContextInterface;
use Traversable;

class PackageConfig extends Config implements PackageConfigInterface
{

    const DEFAULT_PACKAGE_PRIORITY = 0;

    protected string $filename = '';
    protected array $composerData = [];
    protected array $conceptData = [];
    protected string $name = '';
    protected array $requires = [];
    protected array $namespaces = [];

    protected $compabilityValidator = null;

    /**
     * {@inheritDoc}
     */
    public function loadPackage(string $filename): self
    {
        $this->filename = $filename;

        $this->initPackageData(
            $this->readJson($filename)
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function initPackageData(array $data): self
    {
        $this->composerData = $data;

        $this->name = $data['name'];
        $this->namespaces = array_keys($data['autoload']['psr-4'] ?? []);
        $this->requires = array_keys($data['require'] ?? []);
        $this->conceptData = $data['extra']['concept'] ?? [];

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getComposerData(): array
    {
        return $this->composerData;
    }

    /**
     * {@inheritDoc}
     */
    public function getPackageName(): string
    {
        return $this->name;
    }

    /**
     * Get the namespaces
     * 
     * @return Traversable
     */
    protected function getNamespaces(): Traversable
    {
        foreach ($this->namespaces as $namespace) {
            yield $namespace;
        }
    }

    /**
     * Get the requires
     * 
     * @return Traversable
     */
    protected function getRequires(): Traversable
    {
        foreach ($this->requires as $require) {
            yield $require;
        }
    }

    /**
     * Set the external compability validator
     * 
     * @param callable $validator
     * 
     * @return self
     */
    public function setCompabilityValidator(callable $validator): self
    {
        $this->compabilityValidator = $validator;

        return $this;
    }

    /**
     * Get the external compability validator
     * 
     * @return callable
     */
    protected function getCompabilityValidator(): callable
    {
        return $this->compabilityValidator;
    }

    /**
     * Check if the package is compatible
     * 
     * @return bool
     */
    public function isCompatible(): bool
    {
        return isset($this->composerData['extra']['concept']) ? true : false;
    }

    /**
     * Build the package configuration
     * 
     * @return self
     */
    public function build(): self
    {
        if ($this->getPackageName() == 'concept-labs/singleton') {
            $debug = true;
        }
        if (!$this->isCompatible()) {
            return $this;
        }

        $this->buildNamespaceDependency();
        $this->buildPakageDependency();
        $this->buildConceptData();
        $this->includeExternalConfig();

        return $this;
    }

    /**
     * Build the namespace dependency
     */
    protected function buildNamespaceDependency()
    {

        foreach ($this->getNamespaces() as $namespace) {
            $this->mergeTo(
                join(
                    '.',
                    [
                        ConfigContextInterface::NODE_DI_CONFIG,
                        ConfigContextInterface::NODE_NAMESPACE,
                        $namespace,
                        ConfigContextInterface::NODE_DEPENDS
                    ]
                ), 
                [$this->getPackageName() => ["priority" => static::DEFAULT_PACKAGE_PRIORITY]]
            );
        }
    }

    /**
     * build the package dependency
     */
    protected function buildPakageDependency()
    {
        $packageNodePath = join(
            '.',
            [
                ConfigContextInterface::NODE_DI_CONFIG,
                ConfigContextInterface::NODE_PACKAGE,
                $this->getPackageName()
            ]
        );

        $this->mergeTo(
            $packageNodePath,
            []
        );

        foreach ($this->getRequires() as $require) {

            if (!$this->getCompabilityValidator()($require)) {
               continue;
            }

            $this->mergeTo(
                join(
                    '.',
                    [
                        $packageNodePath,
                        ConfigContextInterface::NODE_DEPENDS,
                        $require
                    ]
                ),
                ["priority" => static::DEFAULT_PACKAGE_PRIORITY]
            );
        }
    }

    

    /**
     * Merge collected concept data to the configuration
     * 
     * @return void
     */
    protected function buildConceptData(): void
    {
        $this->mergeTo(
            join(
                '.',
                [
                    ConfigContextInterface::NODE_DI_CONFIG,
                    ConfigContextInterface::NODE_PACKAGE,
                    $this->getPackageName()
                ]
            ),
            $this->conceptData
        );
    }

    /**
     * Includes an external configuration file into the current configuration.
     *
     * This method is responsible for loading and merging an external configuration
     * file into the existing configuration of the application. It ensures that any
     * additional settings or overrides specified in the external file are applied.
     *
     * @return self Returns the current instance of the class for method chaining.
     */
    protected function includeExternalConfig(): self
    {
        $includes = $this->getComposerData()['extra']['concept']['include'] ?? null;
        if (null === $includes) {
            return $this;
        }
        if (!is_array($includes)) {
            $includes = [$includes];
        }

        foreach ($includes as $filename) {
         
            $filename = dirname($this->filename) . '/' . $filename;
            
            if (null === $filename || !is_file($filename) || !is_readable($filename)) {
                return $this;
            }
            
            $config = $this->readJson($filename);
            
            $this->merge($config);
        }
            
        return $this;
    }
}