<?php

    namespace Hudsxn\IocCore\Internals;
    use Closure;
    use Exception;
    use Hudsxn\IocCore\Attribute\Controller;
    use Hudsxn\IocCore\Attribute\Db\DefaultValue;
    use Hudsxn\IocCore\Attribute\Db\LongText;
    use Hudsxn\IocCore\Attribute\Db\Unique;
    use Hudsxn\IocCore\Attribute\Entity;
    use Hudsxn\IocCore\Attribute\Security\EntityPolicy;
    use Hudsxn\IocCore\Attribute\Service;
    use Hudsxn\IocCore\Contracts\IDatabaseProvider;
    use Hudsxn\IocCore\Contracts\ISecurityPolicy;
    use Hudsxn\IocCore\Exception\AccessDeniedException;
    use Hudsxn\IocCore\Provider\ConfigurationProvider;
    use Hudsxn\IocCore\Provider\EventProvider;
    use Hudsxn\IocCore\Traits\DynamicClass;
    use InvalidArgumentException;
    use ReflectionMethod;

    class Injector
    {
        
        private array $services = [];

        private ReflectionMap $reflectionMap;

        public function __construct(ReflectionMap $map)
        {
            $this->reflectionMap = $map;
        }

        public function instantiateClass(string $className, string $type = '', array $extraContext = []): mixed
        {

            if ( isset ( $this->services[ $className ] )) {
                return $this->services[ $className ];
            }

            $def = $this->reflectionMap->getClass($className);

            $constructor = $def->getConstructor();

            $arguments = $this->collectArguments($constructor, $extraContext);

            $controller = $def->getAttributes( Controller::class );

            $instance = new $className(...$arguments);

            if ( count( $controller ) ) {
                // its a controller, if it has a service, setup crud.
                $controller = $controller[0];

                $service = $controller->getArguments()[1] ?? null;

                if ( $service ) {

                    $instance->service = $this->instantiateClass($service);

                    // create the methods.
                    $this->autowireControllerCrud($instance, $this->instantiateClass($service));

                    

                }
            }

            $service = $def->getAttributes(Service::class);

            if ( count( $service ) ) {
                // is a service, if it has an entity, lets autowire the crud methods.

                if ( in_array( DynamicClass::class, $def->getTraitNames() )) {

                    $entityClassName = $service[ 0 ]->getArguments()[ 0 ] ?? null;
                    $dbAdapter       = $service[ 0 ]->getArguments()[ 1 ] ?? null;

                    if ($dbAdapter) {

                        $adapterDef = $this->reflectionMap->getClass($dbAdapter);

                        if ( ! in_array( IDatabaseProvider::class, $adapterDef->getInterfaceNames() ) ) {
                            throw new InvalidArgumentException("Second argument of Service attribute for {$className} expects a class that implements " . IDatabaseProvider::class);
                        } else {
                            if ( $entityClassName && $dbAdapter ) {

                                $this->autowireServiceCrud( $instance, $entityClassName , $dbAdapter);
                            }

                            $instance->init();
                        }

                    }
                }
            }

            return $instance;
        }

        public function collectClosureArguments(Closure $closure, array $extraContext = []): array
        {
            $reflection = new \ReflectionFunction($closure);

            $arguments = [];

            foreach ( $reflection->getParameters() as $parameter ) {

                $type = strval($parameter->getType());

                if ( class_exists( $type )) {
                    // we know its a class now. 

                    if ( $type == ConfigurationProvider::class ) {
                        $arguments[] = ConfigurationProvider::getConfiguration();
                        continue;
                    }
                    
                    $def = $this->reflectionMap->getClass( $type );

                    $svc = $def->getAttributes( Service::class );

                    if ( count($svc) ) {
                        if ( isset( $this->services[ $type ] ) ) {
                            $arguments[] = $this->services[ $type ];
                        } else {
                            // lets cache it for re-use
                            $this->services[ $type ] = $this->instantiateClass( $type );
                            $arguments[] = $this->services[ $type ];
                        }
                        continue;
                    }

                    $entity = $def->getAttributes(Entity::class);

                    if ( count( $entity ) ) {
                        // is an entity. check if extraContent has any data that matches the param name

                        if ( isset( $extraContext[ $parameter->getName() ] )) {
                            
                            if ( ! is_array( $extraContext[ $parameter->getName() ])) {
                                throw new InvalidArgumentException("Property {$parameter->getName()} auto-injection expects an array, received " . gettype( $extraContext[ $parameter->getName() ]));
                            }
                            
                            $deserializer = new Deserializer($this->reflectionMap);

                            $entity = $deserializer->deserialize($type, $extraContext[ $parameter->getName() ]);
                        } else {
                            $arguments[] = new $type();
                        }
                        
                        continue;
                    }

                    // if the constructor is parameter-less, push it in.

                    $constructor = $def->getConstructor();

                    if ( is_null($constructor) ) {
                        // no constructor, inject.

                        $arguments[] = new $type();
                        continue;
                    }

                    if ( ! count( $constructor->getParameters() )) {
                        // no parameters, inject.
                        $arguments[] = new $type();
                        continue;
                    }

                    $objArguments = $this->collectArguments($constructor);

                    $arguments[] = new $type(...$objArguments);

                    continue;
                } else {

                    if ( isset( $extraContext[ $parameter->getName() ] ) ) {
                        $arguments[] = $extraContext[ $parameter->getName() ];
                        continue;
                    }

                    // this property doesn't exist in extra context
                    
                    try{
                        if ( $parameter->getDefaultValue() ) {
                            $arguments[] = $parameter->getDefaultValue();
                            continue;
                        }
                    }catch(Exception $e)
                    {
                        
                    }

                    if ( $parameter->isOptional() ) {
                        $arguments[] = null;
                        continue;
                    }

                    throw new InvalidArgumentException("Parameter {$parameter->getName()} ofclosure is unresolvable, it isn't optional, and doesn't match any contextual values, is this a typo?");

                }

            }
            return $arguments;
        }

        public function collectArguments(?ReflectionMethod $method, array $extraContext = []): array
        {
            if ( is_null( $method )) {
                return []; // constructor can be null.
            }
            if ($method->getName() === '__call') {
                return [];
            }
            if ($method->getName() === '__set') {
                return [];
            }
            if ($method->getName() === '__get') {
                return [];
            }

            $arguments = [];

            foreach ( $method->getParameters() as $parameter ) {

                $type = strval($parameter->getType());

                if ( class_exists( $type )) {
                    // we know its a class now. 

                    if ( $type == ConfigurationProvider::class ) {
                        $arguments[] = ConfigurationProvider::getConfiguration();
                        continue;
                    }

                    if ( $type == ReflectionMap::class ) {
                        $arguments[] = $this->reflectionMap;
                        continue;
                    }

                    if ( $type == Injector::class ) {
                        $arguments[] = $this;
                        continue;
                    }
                    
                    $def = $this->reflectionMap->getClass( $type );

                    $svc = $def->getAttributes( Service::class );

                    if ( count($svc) ) {
                        if ( isset( $this->services[ $type ] ) ) {
                            $arguments[] = $this->services[ $type ];
                        } else {
                            // lets cache it for re-use
                            $this->services[ $type ] = $this->instantiateClass( $type );
                            $arguments[] = $this->services[ $type ];
                        }
                        continue;
                    }

                    $entity = $def->getAttributes(Entity::class);

                    if ( count( $entity ) ) {
                        // is an entity. check if extraContent has any data that matches the param name

                        if ( isset( $extraContext[ $parameter->getName() ] )) {
                            
                            if ( ! is_array( $extraContext[ $parameter->getName() ])) {
                                throw new InvalidArgumentException("Property {$parameter->getName()} auto-injection expects an array, received " . gettype( $extraContext[ $parameter->getName() ]));
                            }
                            
                            $entity = new $type(); // parameter-less constructor, any entity with a constructor should throw an illegal argument exception.

                            foreach ( $extraContext[ $parameter->getName() ] as $k => $v ) {
                                $property = $this->snakeToPascal($k);

                                if (property_exists($type, $property)) {
                                    $entity->{$property} = $v;
                                }
                            }

                            $arguments[] = $entity;
                        } else {
                            $arguments[] = new $type();
                        }
                        
                        continue;
                    }

                    // if the constructor is parameter-less, push it in.

                    $constructor = $def->getConstructor();

                    if ( is_null($constructor) ) {
                        // no constructor, inject.

                        $arguments[] = new $type();
                        continue;
                    }

                    if ( ! count( $constructor->getParameters() )) {
                        // no parameters, inject.
                        $arguments[] = new $type();
                        continue;
                    }

                    $objArguments = $this->collectArguments($constructor);

                    $arguments[] = new $type(...$objArguments);

                    continue;
                } else {

                    if ( isset( $extraContext[ $parameter->getName() ] ) ) {
                        $arguments[] = $extraContext[ $parameter->getName() ];
                        continue;
                    }

                    // this property doesn't exist in extra context
                    
                    try{
                        if ( $parameter->getDefaultValue() ) {
                            $arguments[] = $parameter->getDefaultValue();
                            continue;
                        }
                    }catch(Exception $e) {

                    }
                    
                    if ( isset ( $_GET[$parameter->getName()] )) {
                        $arguments[] = $_GET[$parameter->getName()];
                        continue;
                    }

                    if ( stristr( $parameter->getType(), '?')) {
                        $arguments[] = null;
                        continue;
                    }

                    if ( $parameter->isOptional() ) {
                        $arguments[] = null;
                        continue;
                    }

                    throw new InvalidArgumentException("Parameter {$parameter->getName()} of method {$method->getName()} is unresolvable, it isn't optional, and doesn't match any contextual values, is this a typo?");

                }

            }
            return $arguments;
        }

        protected function autowireControllerCrud( $controller, $service ): void
        {
            $serviceInstance = $this->instantiateClass(is_string($service) ? $service : get_class($service));
            $serviceDef = $this->reflectionMap->getClass(is_string($service) ? $service : get_class($service));

            $serviceEntity = $serviceDef->getAttributes(Service::class);

            if ( ! count( $serviceEntity ) || is_null($serviceEntity[0]->getArguments()[0] ?? null) ) {
                return;
            }

            
            $controller->one = function(int $id) use($serviceInstance) {
                header("Content-Type: application/json");
                return $serviceInstance->one($id);
            };
            $controller->list = function(array $where = [], int $start = 0, int $limit = 20, string $order = 'ASC', string $orderBy = '1') use ($serviceInstance): array {
                header("Content-Type: application/json");
                return $serviceInstance->list($where, $start, $limit, $order, $orderBy);
            };
            $controller->create = function($entity) use ($serviceInstance) {
                header("Content-Type: application/json");
                return $serviceInstance->create($entity);
            };
            $controller->delete = function(int $id) use($serviceInstance) {
                header("Content-Type: application/json");
                return $serviceInstance->delete($id);
            };
            $controller->update = function($entity) use ($serviceInstance) {
                header("Content-Type: application/json");
                return $serviceInstance->update($entity);
            };
        }

        protected function autowireServiceCrud( $service , $entityClassName, $dbAdapter ): void
        {

            $entityDef = $this->reflectionMap->getClass( $entityClassName );

            /** @var \Hudsxn\IocCore\Contracts\IDatabaseProvider */
            $db = $this->instantiateClass( $dbAdapter );

            $entityTable = substr( $entityClassName, 4, strlen( $entityClassName) );
            $entityTable = str_replace( ['\\Entity\\','\\Service\\'], '_', $entityTable );
            $entityTable = str_replace( '\\', '_', $entityTable );
            $entityTable = strtolower( $entityTable );

            $deserializer = new Deserializer($this->reflectionMap);

            $serviceDef = $this->reflectionMap->getClass(get_class($service));
            
            $serviceEntity = $serviceDef->getAttributes(Service::class);

            if ( ! count( $serviceEntity ) || is_null($serviceEntity[0]->getArguments()[0] ?? null) ) {
                return;
            }

            //[name: string, type: string, length?: number, nullable: bool, default: string | AUTO_INCREMENT | CURRENT_TIMESTAMP, unique?: bool]

            $service->init = function() use($entityTable, $entityDef, $db) {

                $fields = [];

                foreach($entityDef->getProperties() as $property) {

                    $type = strval($property->getType());

                    if ($type === 'string')
                    {
                        $text = $property->getAttributes(LongText::class);

                        if ($text) {
                            $type = 'TEXT';
                        }
                    }

                    $nullable = stristr($type, '?');

                    if ($nullable) {
                        $type = str_replace('?', '', $type);
                    }
                    
                    $field = [
                        'name' => $property->getName(),
                        'type' => $type,
                        'nullable' => $nullable
                    ];

                    $default = $property->getAttributes(DefaultValue::class);
                    $unique  = $property->getAttributes(Unique::class);

                    if ( count( $default ) ) {
                        $field['default'] = $default->getParameters()[0];
                    }
                    if ( count( $unique ) ) {
                        $field['unique'] = true;
                    }

                    $fields[] = $field;

                }

                $db->createTable($entityTable, $fields);

            };

            $service->one = function(int $id) use ($db, $entityTable, $entityDef, $entityClassName, $deserializer, $service){

                $item = $db->where( $entityTable, ['Id' => ['operator' => '=', 'value' => $id]], 0, 1 );

                $service->checkPolicy('read', $item);
                
                if ( count ($item) ) {
                    // found it
                    
                    $item = $item[0];

                    return $deserializer->deserialize($entityClassName, $item);

                } else {
                    return null;
                }
            };

            $service->db = $db;

            $service->getTotal = function() use(&$db, $entityTable): int {
                return $db->getTotal($entityTable);
            };

            $service->list = function(array $where = [], int $start = 0, int $limit = 20, $order = 'ASC', $orderBy = '1') use ($db, $entityTable, $entityDef, $entityClassName, $deserializer, $service): array {

                $service->checkPolicy('read', null);

                $items = $db->where($entityTable, $where, $start, $limit, $order, $orderBy);

                $out = [];

                foreach($items as $item) {
                    $out[] = $deserializer->deserialize($entityClassName, $item);
                }

                return $out;
            };

            $inst = $injector = $this;

            $service->checkPolicy = function(string $action, $entity) use($serviceDef, $injector, $entityClassName, $service) {
                $csp = $serviceDef->getAttributes(EntityPolicy::class);

                if (count($csp) != 0) {
                    $csp = $csp[0];

                    $policyProvider = $csp->getArguments()[0] ?? null;

                    if ($policyProvider) {
                        $instance = $injector->instantiateClass($policyProvider);

                        $reqMethods = ['canRead', 'canCreate', 'canUpdate', 'canDelete'];
                        
                        foreach($reqMethods as $method) {
                            if (!method_exists($instance, $method)) {
                                throw new Exception("Security Policy must implement " . ISecurityPolicy::class);
                            }
                        }

                        switch($action) {
                            case 'read':
                                if ($instance->canRead($entity, $entityClassName)) {
                                    return;
                                } else {
                                    throw new AccessDeniedException("User cannot perform {$action} on {$entityClassName}");
                                }
                            case 'create':
                                if ($instance->canCreate($entity, $entityClassName)) {
                                    return;
                                } else {
                                    throw new AccessDeniedException("User cannot perform {$action} on {$entityClassName}");
                                }
                            case 'update':
                                if ($instance->canUpdate($entity, $entityClassName)) {
                                    return;
                                } else {
                                    throw new AccessDeniedException("User cannot perform {$action} on {$entityClassName}");
                                }
                            case 'delete':
                                if ($instance->canDelete($entity, $entityClassName)) {
                                    return;
                                } else {
                                    throw new AccessDeniedException("User cannot perform {$action} on {$entityClassName}");
                                }
                        }
                    }
                }
            };
            
            $service->create = function($entity) use ($db, $entityTable, $entityDef, $entityClassName, $inst, $deserializer, $service) {

                $service->checkPolicy('create', $entity);
                if ( is_array ( $entity ) ) {
                    $entity = $deserializer->deserialize($entityClassName, $entity);
                }

                EventProvider::getEventBus()->dispatch('BeforeCreate', $entity);

                $insert = [];

                foreach ( $entityDef->getProperties() as $property ) {
                    if (isset($entity->{$property->getName()})) {
                        if (strval($property->getType()) === 'bool') {
                            $insert[$property->getName()] = $entity->{$property->getName()} ? 1 : 0;
                        } else {
                            $insert[$property->getName()] = $entity->{$property->getName()};
                        }
                    }
                }

                $id = $db->insert($entityTable, $insert);

                $entity->Id = $id;

                EventProvider::getEventBus()->dispatch('AfterCreate', $entity);

                return $entity;
            };

            $service->delete = function($id) use ($db, $entityTable, $service) {

                $obj = $service->one($id);

                $service->checkPolicy('delete', $obj);

                EventProvider::getEventBus()->dispatch('BeforeDelete', $obj);

                $delete = $db->delete($entityTable, ['Id' => ['operator' => '=', 'value' => $id]]);

                EventProvider::getEventBus()->dispatch('AfterDelete', $obj);

                return $delete;
            };

            $service->update = function($entity) use ($db, $entityTable, $entityDef, $entityClassName, $inst, $deserializer, $service) {


                if ( is_array ( $entity ) ) {
                    $entity = $deserializer->deserialize($entityClassName, $entity);
                }

                $original = $service->one($entity->Id);

                $service->checkPolicy('update', $original);

                EventProvider::getEventBus()->dispatch('BeforeUpdate', $entity, $original);

                $update = [];

                foreach ( $entityDef->getProperties() as $property ) {
                    $update[$property->getName()] = $entity->{$property->getName()};
                }

                $db->update($entityTable, $update, ['Id' => ['operator' => '=', 'value' => $entity->Id]]);

                EventProvider::getEventBus()->dispatch('AfterUpdate', $entity);

                return $entity;
            };
        }

        public function pascalToSnake(string $input): string
        {
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
        }

        public function snakeToPascal( string $input ): string
        {
            return str_replace( 
                ' ', 
                '', 
                ucwords(
                    str_replace(
                        '_', 
                        ' ', 
                        strtolower( $input )
                    )
                )
            );
        }

    }