<?php

    namespace Hudsxn\IocCore\Attribute;
    
    use Attribute;

    #[Attribute]
    class Controller
    {   
        /**
         * The attribute used to mark a controller.
         * @param string $baseUrl - Optional base URL.
         * @param string $service - Use this to label the target service type, this will enable all the CRUD operations for the entity type via the service.
         */
        public function __construct(
            ?string $baseUrl,
            ?string $service
        ) {}
    }