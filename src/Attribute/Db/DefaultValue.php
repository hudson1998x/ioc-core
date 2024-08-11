<?php

    namespace Hudsxn\IocCore\Attribute\Db;
    
    use Attribute;

    #[Attribute]
    class DefaultValue
    {
        public function __construct( mixed $defaultValue )
        {

        }
    }