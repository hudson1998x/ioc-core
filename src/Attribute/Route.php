<?php

    namespace Hudsxn\IocCore\Attribute;

    use Attribute;

    #[Attribute]
    class Route
    {
        public function __construct(
            string $method, 
            string $url,
            ?string $produces = 'application/json',
            ?string $consumes = 'application/json'
        )
        {}
    }