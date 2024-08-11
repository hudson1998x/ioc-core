<?php

    namespace Hudsxn\IocCore\Attribute;
    
    use Attribute;

    #[Attribute]
    class CliMethod
    {   
        /**
         * The attribute used to mark a controller.
         * @param string $actionName - Action name, appended to the base action of CliController.
         */
        public function __construct(
            ?string $actionName
        ) {}
    }