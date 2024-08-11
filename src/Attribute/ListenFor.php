<?php

    namespace Hudsxn\IocCore\Attribute;
    use Attribute;

    #[Attribute]
    class ListenFor
    {
        public function __construct(string $entity, string $event){}
    }