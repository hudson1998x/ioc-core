<?php

    namespace Hudsxn\IocCore\Provider;

    use Closure;
    use Hudsxn\IocCore\Attribute\ListenFor;
    use Hudsxn\IocCore\Internals\ReflectionMap;
    use Hudsxn\IocCore\Internals\Injector;

    class EventProvider
    {
        private static ?self $instance = null;

        private array $events = [];

        private function __construct()
        {
            self::$instance = $this;
        }

        public function addEntity(string $entityName)
        {
            if ( ! isset( $this->events[ $entityName ] )) {
                $this->events[ $entityName ] = [
                    'BeforeCreate' => [],
                    'AfterCreate' => [],
                    'BeforeUpdate' => [], 
                    'AfterUpdate' => [],
                    'BeforeDelete' => [],
                    'AfterDelete' => []
                ];
            }
        }

        public function dispatch($eventName, $entity, $original = null): mixed
        {
            $this->addEntity(get_class($entity));

            foreach ( $this->events[ get_class($entity) ][ $eventName ] as $handler ) {
                $result = $handler($entity, $original);

                if ($result && get_class($result) === get_class($entity)) {
                    $entity = $result;
                }
            }

            return $entity;
        }

        public function subscribe($eventName, $entityName, Closure $handler): self
        {
            $this->addEntity($entityName);

            $this->events[$entityName][$eventName][] = $handler;

            return $this;
        }

        public function init(ReflectionMap $reflectionMap, Injector $injector) 
        {
            foreach($reflectionMap->getAppClasses() as $className) {
                $def = $reflectionMap->getClass($className);

                foreach($def->getMethods() as $method) {
                    $ev = $method->getAttributes(ListenFor::class);

                    if ( count( $ev ) != 0 ) {
                        $event = $ev[0];

                        $entity = $event->getArguments()[0];
                        $eventName = $event->getArguments()[1];

                        $this->subscribe($eventName, $entity, function($entity, $original) use ($reflectionMap, $injector, $className, $method) {
                            $class = $injector->instantiateClass($className);

                            return $class->{$method->getName()}($entity, $original);
                        });
                    }
                }
            }
        }

        public static function getEventBus(): self
        {
            return self::$instance ?? new self();
        }
    }