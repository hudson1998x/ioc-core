<?php

    namespace Hudsxn\IocCore;
    
    use Closure;
    use Hudsxn\IocCore\Internals\ReflectionMap;

    class AppRouter
    {
        private ?Closure $noRouteAction = null;

        private array $preExecute = [];

        private array $postExecute = [];

        private array $routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'PATCH' => [],
            'DELETE' => [],
            'HEAD' => [],
            'OPTIONS' => [] ,
            'PING' => []
        ];

        private ReflectionMap $reflectionMap;

        public function __construct( ReflectionMap $reflectionMap )
        {
            $this->reflectionMap = $reflectionMap;
        }

        public function doNoRoute( string $method, string $url )
        {
            $this->noRouteAction->call($this, ...[$method, $url]);
        }

        /**
         * Adds a route to the application
         * @param string $method - GET, POST, PATCH, PUT, DELETE, HEAD, OPTIONS, PING
         * @param string $url - the URL path
         * @param string $className - the target controller
         * @param string $methodName - the target method
         * @return \Hudsxn\IocCore\AppRouter
         */
        public function addRoute( string $method, string $url, string $className, string $methodName ): self
        {
            $method = strtoupper( $method );

            $this->routes[ $method ][ $url ] = [$className, $methodName];

            return $this;
        }

        public function getRoute( string $method, string $path ): array
        {
            $output = [];

            if ( $path != '' && $path[ strlen( $path ) - 1 ] == '/' ) {
                $path = substr( $path, 0, strlen($path) - 1 );
                // remove trailing /
            }

            $match = true;

            foreach ( $this->routes[ $method ] as $route => $handler ) {

                if (!$match) {
                    // reset it.
                    $match = true;
                }

                $urlSegments   = explode('/', $path);
                $routeSegments = explode('/', $route);

                if ( count( $urlSegments ) != count( $routeSegments )) {
                    continue;
                }

                $pathParameters = [];

                for($i = 0;$i < count($urlSegments);$i++) {

                    $urlSegment   = $urlSegments[$i];
                    $routeSegment = $routeSegments[$i];

                    if ( $urlSegment === $routeSegment ) {
                        // exact EQ.
                        continue;
                    }

                    if ( strlen( $urlSegment ) == 0 && strlen( $routeSegment ) == 0 ) {
                        // happens for the first / in the URL. no other side effects.
                        continue;
                    }

                    if ( in_array( $routeSegment[ 0 ], [ ':', '{' ] )) {
                        $pathParameters[
                            str_replace( [':', '{', '}'], '', $routeSegment )
                        ] = $urlSegment;
                        continue;
                    }
                    
                    $match = false;
                    break;

                }

                if ( $match ) {
                    return [...$handler, $pathParameters];
                }
            }
            if ( $this->noRouteAction ) {
                
                die($this->noRouteAction->call($this, ...[$method, $path]));

            } else {
                
                http_response_code(404);

                die('<h1>404</h1>');
            }            
        }

        public function beforeRequest(Closure $handler): self
        {
            $this->preExecute[] = $handler;
            return $this;
        }

        public function afterRequest(Closure $handler): self
        {
            $this->postExecute[] = $handler;
            return $this;
        }

        public function setNoRouteAction(Closure $handler): self
        {
            $this->noRouteAction = $handler;

            return $this;
        }

        public function __toString(): string
        {
            $out = "AppRouter {\n";

            foreach($this->routes as $method => $route) {
                $out .= "\t{$method} \{\n";
                
                foreach($route as $url => $data) {
                    $out .= "\t\t'{$url}': " . str_replace("\n", "\n\t\t\t", var_export($data, true)) . ",\n";
                }

                $out .= "\t}, \n";
            }

            $out .= "}";

            return $out;
        }
    }