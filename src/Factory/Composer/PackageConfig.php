<?php
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
     * Build the package
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
     * Merge the data to the config
     * 
     * @param string $path
     * @param mixed $data
     * 
     * @return self
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
     * Merge the data to the config
     * 
     * @param string $path
     * @param mixed $data
     * 
     * @return self
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
            if ($require == 'concept-labs/singleton') {
                $debug = true;
            }
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
     * Merge the data to the config
     * 
     * @param string $path
     * @param mixed $data
     * 
     * @return self
     */
    protected function buildConceptData()
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
     * Include external config
     * 
     * @return self
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