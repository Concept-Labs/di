<?php
namespace Concept\Di\Factory\Context;

use Concept\Config\Config;
use Concept\Di\Factory\Exception\LogicException;
use Concept\PathAccess\PathAccessInterface;

class ConfigContext extends Config implements ConfigContextInterface
{
    protected array $dependencyStack = [];


    public function getServicePreferenceConfigPath(string $serviceId): string
    {
        return $this->path(ConfigContextInterface::NODE_PREFERENCE, $serviceId);
    }

    
    public function getServicePreferenceConfig(string $serviceId): self
    {
        if (!$this->has($this->getServicePreferenceConfigPath($serviceId))) {
            return $this->withData([
                    ConfigContextInterface::NODE_CLASS => $serviceId
                ]);
            // throw new LogicException(
            //     sprintf(_('Service preference config not found for service ID "%s"'), $serviceId)
            // );
        }
        return $this->from($this->getServicePreferenceConfigPath($serviceId));
    }

    
    public function getPreferenceClass(string $serviceId): string
    {
        return $this->getServicePreferenceConfig($serviceId)->get(ConfigContextInterface::NODE_CLASS);
    }

   
    public function getServiceParametersConfig(string $serviceId): self
    {
        $preferenceConfig = $this->getServicePreferenceConfig($serviceId);

        if (!$preferenceConfig->has(ConfigContextInterface::NODE_PARAMETERS)) {
            return $preferenceConfig->withData([]);
        }

        return $preferenceConfig->from(ConfigContextInterface::NODE_PARAMETERS);
    }


    
    public function buildServiceContext($serviceId, array $config = []): self
    {
        /**
         * Optionally merge the config
         */
        //$this->merge($config);
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
        $preferenceConfig = $this->getServicePreferenceConfig($serviceId);
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
        
        return $this;
    }

    
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
     * Merge dependency context
     * Configuration node "depends" may contain a list of packages to merge:
     *   "depends": {"package1":{...}, "package2":{...}}
     * 
     * @param PathAccessInterface $config
     * 
     * @return self
     * 
     * @throws LogicException
     */
    protected function mergeDependencyContext(PathAccessInterface $config): self
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
       
        return $this;
    }

    /**
     * Merge reference context
     * Preference configuration may contain a reference to another configuration:
     *    "preference": {
     *      "reference": "path/to/reference"
     *   }
     * 
     * @param ConfigContextInterface $config
     * 
     * @return void
     * 
     * @throws LogicException
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
     * Preference configuration may contain a sub DI configuration:
     * {
     *     "preference": {
     *        "di": {
     *          ...
     *       }
     * }
     * 
     * @param ConfigContextInterface $config
     * 
     * @return void
     * 
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