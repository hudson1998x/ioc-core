<?php

    namespace Hudsxn\IocCore\Attribute;
    
    use Attribute;

    #[Attribute]
    class CliController
    {   
        /**
         * The attribute used to mark a controller.
         * @param string $baseUrl - Base action.
         */
        public function __construct(
            ?string $baseAction
        ) {}
    }