<?php
//Backup
// protected function applyServiceRuntimeContext(): self
//     {
//         $reflection = $this->getServiceReflection();

//         /**
//          * Inline DI config.
//          * This is a constant in the service class
//          * It must be an array
//          * Config structure is the same as the main config
//          */
//         if ($reflection->hasConstant(ConfigContextInterface::INLINE_DI_CONFIG_CONSTANT)) {
//             $diConfig = $reflection->getConstant(ConfigContextInterface::INLINE_DI_CONFIG_CONSTANT);
//             if (!is_array($diConfig)) {
//                 throw new LogicException(
//                     sprintf(_('Constant "%s" must be an array'), ConfigContextInterface::INLINE_DI_CONFIG_CONSTANT)
//                 );
//             }

//             $this->getConfigContext()
//                 ->merge($diConfig);
//         }
        
//         /**
//          * Inline DI config class method.
//          * This is a constant in the service class
//          * It must be a string and the method with this name must exist in the service class
//          * The method must be static
//          * The method must return an array
//          * The array structure is the same as the main config
//          */
//         if ($reflection->hasConstant(ConfigContextInterface::DYNAMIC_DI_CONFIG_METHOD)) {
//             $method = $reflection->getConstant(ConfigContextInterface::DYNAMIC_DI_CONFIG_METHOD);
//             if ($reflection->hasMethod($method)) {
               
//                 $methodReflection = $reflection->getMethod($method);
//                 if (!$methodReflection->isStatic()) {
//                     throw new LogicException(
//                         sprintf(_('Method "%s" must be static in class "%s"'), $method, $reflection->getName())
//                     );
//                 }

//                 $methodReflection->setAccessible(true);
//                 $diConfig = $methodReflection->invoke(null);
//                 if (!is_array($diConfig)) {
//                     throw new LogicException(
//                         sprintf(_('Method "%s" must return an array in class "%s"'), $method, $reflection->getName())
//                     );
//                 }

//                 $this->getConfigContext()
//                     ->merge($diConfig);
//             }
//         }

//         return $this;
//     }