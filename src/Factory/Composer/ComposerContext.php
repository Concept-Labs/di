<?php
/**
 * This class is part of the Concept Labs Dependency Injection package.
 * It provides context for Composer, which is used to manage dependencies
 * and autoloading in PHP projects.
 *
 * The ComposerContext class is responsible for interacting with Composer
 * to retrieve and manage package information, dependencies, and other
 * related tasks within the application.
 *
 * @package     Concept\Di
 * @category    DependencyInjection
 * @author      Victor Galitsky (mtr) concept.galitsky@gmail.com
 * @license     https://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 * @link        https://github.com/concept-labs/di
*/
namespace Concept\Di\Factory\Composer;

use Concept\Di\Factory\Composer\PackageConfig;
use Concept\Di\Factory\Composer\PackageConfigInterface;
use Concept\Di\Factory\Context\ConfigContext;

class ComposerContext extends ConfigContext implements ComposerContextInterface
{
    /**
     * @var string|null
     */
    static private ?string $vendorDir = null;
    /**
     * @var array
     */
    protected array $packages = [];

    /**
     * {@inheritDoc}
    
     * phpdoc
     */
    public function buildComposerContext(): static
    {
        $this->collectPackages();

        foreach ($this->getPackages() as $package) {
            $this->merge(
                $package->build()
            );
        }
        return $this;
    }

    /**
     * Get loaded packages
     * 
     * @return array
     */
    protected function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * Collect packages
     * 
     * @return static
     */
    protected function collectPackages(): static
    {
        $composerFiles = glob(static::getVendorDir() . '/*/*/composer.json');
        $composerFiles[] = dirname(static::getVendorDir()) . '/composer.json';
        foreach ($composerFiles as $composerFile) {
            $packageConfig = $this->createPackageConfig($composerFile);
            if (!$packageConfig->isCompatible()) {
                continue;
            }
            $this->packages[$packageConfig->getPackageName()] = $this->createPackageConfig($composerFile);
        }

        array_filter($this->packages);

        return $this;
    }

    /**
     * Create package config instance
     * 
     * @param string $path
     * @return PackageConfigInterface|null
     */
    protected function createPackageConfig(string $path): ?PackageConfigInterface
    {
        $packageConfig = new PackageConfig();
        $packageConfig->loadPackage($path);

        $packageConfig->setCompabilityValidator(
            function (string $packageName) {
                return $this->isPackageCompatible($packageName);
            }
        );

        return $packageConfig;
    }

    /**
     * Check if the package is compatible
     * 
     * @param string $packageName
     * @return bool
     */
    protected function isPackageCompatible(string $packageName): bool
    {
        if (!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $packageName)) {
            return false;
        }
        return $this->hasPackage($packageName) && $this->getPackage($packageName)->isCompatible();
    }

    /**
     * Get package instance
     * 
     * @param string $packageName
     * @return PackageConfigInterface
     */
    protected function getPackage(string $packageName): PackageConfigInterface
    {
        return $this->packages[$packageName];
    }

    /**
     * Check if the package is loaded
     * 
     * @param string $packageName
     * @return bool
     */
    protected function hasPackage(string $packageName): bool
    {
        return isset($this->packages[$packageName]);
    }

    /**
     * Get vendor directory
     * 
     * @return string|null
     */
    public static function getVendorDir(): ?string
    {
        if (null !== static::$vendorDir) {
            return static::$vendorDir;
        }
        if (!class_exists('Composer\Autoload\ClassLoader')) {
            throw new \RuntimeException('Composer is not loaded');
            return null;
        }

        
        $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        static::$vendorDir = dirname(dirname($reflection->getFileName()));

        return static::$vendorDir;
    }
    
}