<?php

    namespace Hudsxn\IocCore\Internals;

    use ReflectionClass;

    /**
     * This class holds information about class reflections, this avoids having to re-instantiate a new ReflectionClass Object every time its needed
     */
    class ReflectionMap
    {
        private array $reflectionObjects = []; 

        private array $appClasses = [];

        public function getAppClasses(): array 
        {
            if ( count( $this->appClasses ) == 0 ) {
                $classes = [];

                foreach ( get_declared_classes() as $class ) {
                    if ( substr( $class, 0, 4 ) === 'App\\' ) {
                        $classes[] = $class;
                    }
                }

                $this->appClasses = $classes;
            }
            return $this->appClasses;
        }

        public function getClass( string $class ) : ReflectionClass {
            
            if ( ! isset( $this->reflectionObjects[ $class ] ) ) {
                $this->reflectionObjects[ $class ] = new ReflectionClass( $class );
            }

            return $this->reflectionObjects[ $class ];
        
        }

        public function getClassesWithAnnotation( string $annotation ): array
        {
            $matched = [];

            foreach( $this->getAppClasses() as $class ) {

                $reflection = $this->getClass( $class );

                $attributes = $reflection->getAttributes( $annotation );

                if ( count( $attributes ) != 0 ) {
                    $matched[] = $reflection;
                }
            }
            
            return $matched;
        }
    }