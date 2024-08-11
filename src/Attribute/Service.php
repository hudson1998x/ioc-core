<?php

    namespace Hudsxn\IocCore\Attribute;
    
    use Attribute;

    #[Attribute]
    class Service
    {   
        /**
         * The attribute used to mark a service.
         * @param string $entity - Use this to label the target entity type, this will enable all the CRUD operations for the entity type.
         * @param string $database - The database class used to perform CRUD on the entity, please note: service must implement the IDatabaseProvider contract.
         */
        public function __construct(
            ?string $entity,
            ?string $database
        ) {}
    }