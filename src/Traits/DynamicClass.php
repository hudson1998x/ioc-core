<?php

    namespace Hudsxn\IocCore\Traits;

    use BadMethodCallException;
    use Closure;
    use Exception;

    trait DynamicClass
    {
        /**
         * Map containing injected methods.
         * @var Closure[]
         */
        private array $injectedMethods = [];
        
        /**
         * Map containing injected variables
         * @var mixed[]
         */
        private array $injectedVars    = [];

        public function __set( string $name, mixed $value ): void
        {
            if ( $value instanceof Closure ) {
                $this->injectedMethods[ $name ] = $value;
            } else {
                $this->injectedVars[ $name ] = $value;
            }
        }

        public function __get( string $name ): mixed
        {
            if ( isset( $this->injectedVars[ $name ] )) {
                return $this->injectedVars[ $name ];
            }
            if ( isset( $this->injectedMethods[ $name ] )) {
                return $this->injectedMethods[ $name ];
            }
            throw new Exception("The properrty {$name} does not exist on class " . get_class($this));
        }

        public function __call( string $method, array $args ): mixed
        {
            if ( isset( $this->injectedMethods[ $method ] ) ) {
                return $this->injectedMethods[ $method ]->call($this, ...$args);
            }

            throw new BadMethodCallException( "The method {$method} does not exist on class " . get_class($this) );
        }

    }