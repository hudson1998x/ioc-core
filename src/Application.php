<?php

    namespace Hudsxn\IocCore;

    use Closure;
    use Exception;
    use Hudsxn\IocCore\Attribute\CliController;
    use Hudsxn\IocCore\Attribute\CliMethod;
    use Hudsxn\IocCore\Attribute\Controller;
    use Hudsxn\IocCore\Attribute\Entity;
    use Hudsxn\IocCore\Attribute\Route;
    use Hudsxn\IocCore\Attribute\Service;
    use Hudsxn\IocCore\CoreCli\Build\CreateEntity;
    use Hudsxn\IocCore\CoreCli\Build\CreateReactApp;
    use Hudsxn\IocCore\CoreCli\Build\GenerateFrontendJs;
    use Hudsxn\IocCore\Internals\Injector;
    use Hudsxn\IocCore\Internals\ReflectionMap;
    use Hudsxn\IocCore\Provider\EventProvider;
    use Hudsxn\IocCore\Traits\DynamicClass;
    use InvalidArgumentException;
    use ReflectionClass;

    /**
     * @author - John Hudson
     * @description - this launches an application. Run with either 'web', 'cli' or 'cron' for different routes.
     */

    class Application
    {

        private ReflectionMap $reflectionMap;

        private Injector $injector;

        private AppRouter $appRouter;

        /**
         * Creates an instance of application
         * @param string $sourceDirectory - input directory
         * @param string $runType - accepted values, 'web' | 'cli' | 'cron'
         * @throws Exception - when an incorrect $runType is supplied.
         */
        public function __construct( string $sourceDirectory, string $runType = 'web' , ?Closure $injectedMethod, $argv = [])
        {

            if ( ! in_array( $runType, [ 'web', 'cli', 'cron' ] )) {
                throw new Exception( "Failed to start application, " . get_class($this) . "::__construct expects parameter 2 to be a string, allowing 'web', 'cli' and 'cron' as a value only" );
            }

            $this->reflectionMap = new ReflectionMap();
            $this->injector = new Injector($this->reflectionMap);

            $includes = $this->getFiles( $sourceDirectory );

            foreach ( $includes as $include ) {
                require_once $include;
            }

            if ( $runType === 'web' ) {

                $this->appRouter = new AppRouter( $this->reflectionMap );
                
                // make sure a session is available. 
                session_start();

                // load controllers here.
                /** @var ReflectionClass[] */
                $controllers = $this->reflectionMap->getClassesWithAnnotation( Controller::class );

                foreach ( $controllers as $controller ) {
                    
                    /**
                     * @var \ReflectionAttribute
                     */
                    $attribute = $controller->getAttributes( Controller::class )[0];

                    // should only be one, its matched though or wouldn't show here.

                    $arguments    = $attribute->getArguments();

                    $baseUrl      = $arguments[ 0 ] ?? '';
                    $serviceClass = $arguments[ 1 ] ?? null;

                    

                    if ( $serviceClass ){
                        // check it inherits the dynamicclass trait.

                        if ( ! in_array (DynamicClass::class, $controller->getTraitNames() )) {
                            throw new InvalidArgumentException("In order to use a service class on a controller for crud, the controller must use the trait: " . DynamicClass::class);
                        }

                        $serviceReflection = $this->reflectionMap->getClass( $serviceClass );

                        $serviceEntity = $serviceReflection->getAttributes(Service::class);

                        if ( ! count( $serviceEntity ) || is_null($serviceEntity[0]->getArguments()[0] ?? null) ) {
                            
                        } else {

                            if ( ! in_array (DynamicClass::class, $serviceReflection->getTraitNames() )) {
                                throw new InvalidArgumentException("In order to use a service class on a controller for crud, the service must use the trait: " . DynamicClass::class);
                            }

                            $this->appRouter->addRoute( 'GET', $baseUrl . '/one/:id', $controller->getName(), 'one' );
                            $this->appRouter->addRoute( 'POST', $baseUrl, $controller->getName(), 'list' );
                            $this->appRouter->addRoute( 'PUT', $baseUrl, $controller->getName(), 'create' );
                            $this->appRouter->addRoute( 'PATCH', $baseUrl . '/:id', $controller->getName(), 'update' );
                            $this->appRouter->addRoute( 'DELETE', $baseUrl . '/:id', $controller->getName(), 'delete' );
                        }
                        
                    }

                    foreach ( $controller->getMethods() as $method ) {

                        $route = $method->getAttributes(Route::class);

                        if ( count($route) != 0 ) {
                            // we have a route.

                            $route  = $route[0];

                            $methodType = $route->getArguments()[ 0 ] ?? null;
                            $url    = $route->getArguments()[ 1 ] ?? null;

                            if ( ! $method || ! $url ) {

                                // don't show an error, just don't add it. #StayToxic
                                // jk, just don't show an error due to it could be a 
                                // incomplete method, just show a 404 any dev will
                                // know to check that method should it 404.

                                
                                continue;
                            }

                            $this->appRouter->addRoute( $methodType, $baseUrl . '/' . $url, $controller->getName(), $method->getName() );
                        }

                    }
                }
            }

            // load controllers, services etc..
            $services = $this->reflectionMap->getClassesWithAnnotation( Service::class );

            foreach ( $services as $serviceClass ) {
                
            }

            EventProvider::getEventBus()->init($this->reflectionMap, $this->injector);

            if ( $injectedMethod ) {
                $injectedMethod->call($this);
            }

            if ( $runType === 'web' ) {

                [ $controller, $methodName, $pathParameters ] = $this->appRouter->getRoute( $_SERVER[ 'REQUEST_METHOD' ], explode( '?', $_SERVER[ 'REQUEST_URI' ] )[0] );

                $instance = $this->injector->instantiateClass($controller);
                $def = $this->reflectionMap->getClass($controller);

                $method;
                
                try {
                    $method = $def->getMethod($methodName);
                }catch(Exception $e) {

                }

                $context = [];

                $post = json_decode(@file_get_contents("php://input"), true) ?? [];

                $context = array_merge($context, $post, $pathParameters);#


                $response;

                if ( ! is_null ( $method ) ) {
                    // method exists, lets go.

                    if ( in_array( $method->getName(), ['__call', '__set', '__get']) ) {
                        // is an injected method.
                        $args = $this->injector->collectClosureArguments($instance->{$methodName}, $context);

                        $response = $instance->__call($methodName, $args);
                    } else {

                        $args = $this->injector->collectArguments($method, $context);
                        

                        if (method_exists($instance, $method->getName())) {
                            $response = $instance->{$method->getName()}(...$args);
                        } else {
                            $this->appRouter->doNoRoute($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
                        }

                    }
                    

                } else if ( in_array( DynamicClass::class, $def->getTraitNames() )) {
                    // is an injected method.
                    $args = $this->injector->collectClosureArguments($instance->{$methodName}, $context);

                    $response = $instance->__call($methodName, $args);
                } else {
                    $this->appRouter->doNoRoute($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
                    die(); // if it doesn't already do it.
                }
                
                // Next: lets convert that response

                switch ( true ) {

                    case is_object( $response ):

                        if ( get_class( $response ) == 'stdClass' ) {
                            header( "Content-Type: application/json" );
                            die( json_encode( $response ) );
                        }

                        $def = $this->reflectionMap->getClass( get_class( $response ) );

                        // check if an entity.

                        if ( count( $def->getAttributes( Entity::class )) ) {
                            // its an entity.
                            header( "Content-Type: application/json" );
                            die( json_encode( $response ) );
                        }

                        die(strval($response));

                    case is_array( $response ):
                    case is_scalar( $response ):
                        header( "Content-Type: application/json" );
                        die( json_encode( $response ) );
                    
                }

            } else if ( $runType === 'cli' ) {
                $this->handleCli($argv);
            }
        }

        protected function handleCli( $argv ) {
            $action = $argv[ 1 ];
            $params = [];

            for ( $i = 2;$i < count( $argv );$i++ ) {
                [ $k, $v ] = explode( "=", $argv[ $i ] );
                $k = str_replace( '-', '', $k );

                $params[ $k ] = $v;
            }

            $handled = false;

            $allTargets = [ 
                GenerateFrontendJs::class, 
                CreateReactApp::class, 
                CreateEntity::class,
                ...$this->reflectionMap->getClassesWithAnnotation(CliController::class) ];

            foreach ( $allTargets as $className ) {

                $target = $this->reflectionMap->getClass($className);

                $cliController = $target->getAttributes(CliController::class);

                $cliController = $cliController[0];

                $basePath = $cliController->getArguments()[0] ?? null;

                foreach ( $target->getMethods() as $method )
                {

                    $isAction = $method->getAttributes( CliMethod::class );

                    if ( count( $isAction ) != 0 ) {
                        // is a CLI action.

                        $actionName = $isAction[0]->getArguments()[0] ?? null;

                        if (!$actionName) {
                            continue;
                        }

                        $fullAction = ($basePath ? $basePath . '/' : '') . $actionName;

                        if ($fullAction == $action) {

                            $instance = $this->injector->instantiateClass($target->getName(), 'CliController', $params);

                            $arguments = $this->injector->collectArguments($method, $params);

                            $instance->{$method->getName()}(...$arguments);
                            $handled = true;

                            break 2;
                        }

                    }

                }

            }

            if (!$handled) {
                exit ('No action occured, unknown command ' . $action);
            }
        }

        public function getApplicationRouter(): AppRouter
        {
            return $this->appRouter;
        }

        /**
         * returns a list of PHP files to include at app launch.
         * @param string $dir - the directory to scan
         * @return array<string> - the list of files found
         */
        private function getFiles( string $dir ): array
        {
            $out = [];

            if ( is_file( $dir ) || ! file_exists( $dir ) ) {
                return $out;
            }

            foreach ( glob( "{$dir}/*" ) as $entry ) {
                if ( is_dir( $entry )) {
                    $out = array_merge( $out, $this->getFiles( $entry ) );
                } else {
                    if ( str_ends_with( $entry, '.php' ) ) {
                        $out[] = $entry;
                    }
                }
            }

            return $out;
        }
    }