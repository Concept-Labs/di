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
namespace Concept\Di\Factory\Context;

use Concept\Config\Config;
use Concept\Di\Factory\Exception\LogicException;
use Concept\PathAccess\PathAccessInterface;

class ConfigContext extends Config implements ConfigContextInterface
{
    private array $dependencyStack = [];

    /**
     * {@inheritDoc}
     *
     * This method is part of the Factory Context within the Dependency Injection (DI) system.
     * It is responsible for providing configuration context necessary for the creation of 
     * objects within the DI container. The configuration context typically includes parameters 
     * and settings that influence how objects are instantiated and wired together.
     *
     * This method retrieves the service configuration for a given service ID. If a preference 
     * configuration for the service ID is not found, it returns a default configuration where 
     * the service ID is used as the class name. The method also validates the preference 
     * configuration if it exists.
     *
     * @param string $serviceId The unique identifier of the service whose configuration is to be retrieved.
     * @return PathAccessInterface The configuration for the specified service.
     * @throws LogicException If a circular dependency is detected or if the package path is not found.
     */
    public function getServiceConfig(string $serviceId): PathAccessInterface
    {
        $preferenceConfigPath = $this->path(ConfigContextInterface::NODE_PREFERENCE, $serviceId);

        if (!$this->has($preferenceConfigPath)) {
            return $this->withData([
                /**
                 * Allow service ID as the class name by default
                 */
                    ConfigContextInterface::NODE_CLASS => $serviceId
                ]);
            // throw new LogicException(
            //     sprintf(_('Service preference config not found for service ID "%s"'), $serviceId)
            // );
        }
        $preferenceConfig = $this->from($preferenceConfigPath);

        $this->validatePreferenceConfig($preferenceConfig);

        return $preferenceConfig;
    }

    /**
     * {@inheritDoc}
     * 
     * Builds the service context for the given service ID with optional configuration overrides.
     *
     * This method initializes and configures the service context based on the provided service ID.
     * Optionally, configuration overrides can be passed to customize the context.
     *
     * @param string $serviceId The unique identifier of the service for which the context is being built.
     * @param array $configOverrides An optional associative array of configuration overrides to customize the service context.
     * 
     * @return self Returns the current instance of the ConfigContext with the built service context.
     */
    public function buildServiceContext($serviceId, array $configOverrides = []): self
    {
        /**
         * Merge namespace context first.
         * Preference config may be located in the namespace node
         */
        $this->mergeNamespaceContext($serviceId);


        if (!$this->has(ConfigContextInterface::NODE_PREFERENCE, $serviceId)) {
            /**
             * No preference found, return the default configuration:
             * Service ID as the class name
             */
            $this->merge([
                ConfigContextInterface::NODE_PREFERENCE => [
                    $serviceId => [ConfigContextInterface::NODE_CLASS => $serviceId]
                ]
            ]);

            return $this;
        }

        /**
         * Preference config
         */
        $preferenceConfig = $this->getServiceConfig($serviceId);
        /**
         * Apply dependency context
         */
        $this->mergeDependencyContext($preferenceConfig);

        /**
         * Apply reference context
         */
        $this->mergeReferenceContext($preferenceConfig);

        /**
         * Apply sub DI context
         */
        $this->mergeSubDiContext($preferenceConfig);

        if (!$preferenceConfig->has(ConfigContextInterface::NODE_CLASS)) {
            /**
             * No class found, use the service ID
             */
            $preferenceConfig->merge([ConfigContextInterface::NODE_CLASS => $serviceId]);
        }


        /**
         * Apply resolved preference config to the factory config
         */
        $this->merge([
            ConfigContextInterface::NODE_PREFERENCE => [
                    $serviceId => $preferenceConfig->asArray()
                ]
        ]);

        /**
         * Optionally merge the config overrides
         */
        $this->merge($configOverrides);
        
        return $this;
    }

    /**
     * Validate preference config
     * 
     * @param PathAccessInterface $config
     * 
     * @return void
     */
    protected function validatePreferenceConfig(PathAccessInterface $config): void
    {
        /**
         * @todo Implement
         */
        return;
    }
    
    /**
     * Merges the namespace context for a given service ID.
     * 
     * This method is responsible for merging the context associated with a specific
     * service identifier. It ensures that the namespace context is properly combined
     * and updated based on the provided service ID.
     * 
     * @param string $serviceId The identifier of the service whose namespace context
     *                          needs to be merged. This should be a unique string
     *                          representing the service within the application.
     * 
     * @return void This method does not return any value.
     */
    /**
     * Merge namespace context
     * 
     * @param string $serviceId
     * 
     * @return void
     */
    protected function mergeNamespaceContext(string $serviceId): void
    {
        $namespaceParts = explode('\\', $serviceId);
        $namespace = '';
        while (count($namespaceParts) > 0) {
            $namespace = ltrim(
                join('\\', [$namespace, array_shift($namespaceParts)]) ?: '\\',
                '\\'
            );
            $namespacePath = $this->path(
                ConfigContextInterface::NODE_NAMESPACE, $namespace
            ) . '\\';
            
            if ($this->has($namespacePath) && !$this->get($namespacePath, '___merged')) 
            {
                /**
                 * Found namespace config
                 */
                $namespaceConfig = $this->from($namespacePath);

                /**
                 * Build dependency context recursively
                 * Namespace node may contain a list of packages to merge:
                 *  "depends": {"package1": {...}, "package2": {..}}
                 */
                $this->mergeDependencyContext($namespaceConfig);

                /**
                 * Merge namespace config
                 */
                $this->merge($namespaceConfig->asArray());
                
                $this->set($namespacePath.'.___merged', true);
            }
        }
    }

     /**
     * Merges the dependency context from the given configuration.
     *
     * The configuration node "depends" may contain a list of packages to merge,
     * structured as follows:
     *   "depends": {"package1":{...}, "package2":{...}}
     *
     * This method processes the "depends" node and merges the specified packages
     * into the current context.
     *
     * @param PathAccessInterface $config The configuration object containing the "depends" node.
     *
     * @return self Returns the current instance for method chaining.
     *
     * @throws LogicException If there is an error during the merging process.
     */
    protected function mergeDependencyContext(PathAccessInterface $config): void
    {
        $dependencies = $config->get(ConfigContextInterface::NODE_DEPENDS) ?? [];
        /**
         * Sort dependencies by priority
         */
        uasort($dependencies, function ($a, $b) {
            if (!isset($a['priority']) || !isset($b['priority'])) {
                return 0;
            }
            return $a['priority'] <=> $b['priority'];
        });

        foreach ($dependencies ?? [] as $package => $packageData) {

            if (in_array($package, $this->dependencyStack)) {
                throw new LogicException(
                    sprintf(_('Circular package dependency detected for package "%s"'), $package)
                );
            }

            $packagePath = $this->path(ConfigContextInterface::NODE_PACKAGE, $package);
            
            if (!$this->has($packagePath)) {
                throw new LogicException(
                    sprintf(_('package path not found "%s"'), $packagePath)
                );
            }

            if ($this->has($packagePath.'.'.'___merged')) {
                continue;
            }

            array_push($this->dependencyStack, $package);

            $packageConfig = $this->from($packagePath);
            
            /**
             * Build dependency context recursively
             */
            $this->mergeDependencyContext($packageConfig);

            /**
             * Merge package config
             */
            $packageConfigData = $packageConfig->asArray();
            unset($packageConfigData[ConfigContextInterface::NODE_DEPENDS]);
            $this->merge($packageConfig->asArray());

            $this->set($packagePath.'.'.'___merged', true);
            

            array_pop($this->dependencyStack);
        }
//@TODO: Think
        //$config->unset(ConfigContextInterface::NODE_DEPENDS);
       
    }

    /**
     * Merge reference context.
     *
     * This method merges the reference context into the current configuration context.
     * The preference configuration may contain a reference to another configuration,
     * specified in the following format:
     * 
     * "preference": {
     *     "reference": "path/to/reference"
     * }
     *
     * This method will resolve the reference and merge the referenced configuration
     * into the current configuration context.
     *
     * @param ConfigContextInterface $config The configuration context to merge the reference into.
     *
     * @return void
     *
     * @throws LogicException If the reference cannot be resolved or if there is a circular reference.
     */
    protected function mergeReferenceContext(ConfigContextInterface $config): void
    {
        if ($config->has(ConfigContextInterface::NODE_REFERENCE)) {
            /**
             * Check reference relative path
             */
            $referencePath = $this->path(
                ConfigContextInterface::NODE_PREFERENCE, 
                $config->get(ConfigContextInterface::NODE_REFERENCE)
            );


            if (!$this->has($referencePath)) {
                /**
                 * Check absolute path
                 */
                $referencePath = $config->get(ConfigContextInterface::NODE_REFERENCE);
                if (!$this->has($referencePath)) {
                    throw new LogicException(
                        sprintf(_('Reference path not found "%s"'), $referencePath)
                    );
                }
            }

            /**
             * Apply reference config to the preference config
             */
            $data = $this->get($referencePath);
            
            if (empty($data)) {
                throw new LogicException(
                    sprintf(_('Reference config is empty "%s"'), $referencePath)
                );
            }
            
            $config->merge($data);

            /**
             * Unset reference node (optional)
             */
            $config->unset(ConfigContextInterface::NODE_REFERENCE);
        }
    }

    /**
     * Merge sub DI context
     *
     * This method merges a sub Dependency Injection (DI) context into the current configuration context.
     * The preference configuration may contain a sub DI configuration, which is represented as a nested
     * structure within the "di" key. The method processes this nested DI configuration and 
     * integrates it into the current context.
     *
     * Example of preference configuration with sub DI context:
     * {
     *     "preference": {
     *        "di": {
     *          // Sub DI configuration details
     *        }
     *     }
     * }
     *
     * @param ConfigContextInterface $config The configuration context that contains the sub DI configuration
     *
     * @return void
     */
    protected function mergeSubDiContext(ConfigContextInterface $config): void
    {
        if ($config->has(ConfigContextInterface::NODE_DI_CONFIG)) {
            $this
                ->merge(
                    $config->get(ConfigContextInterface::NODE_DI_CONFIG)
                );
        }
    }
}