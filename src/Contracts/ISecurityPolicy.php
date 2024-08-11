<?php

    namespace Hudsxn\IocCore\Contracts;

    interface ISecurityPolicy
    {
        public function canRead(mixed $entity, string $entityClassName): bool;

        public function canCreate(mixed $entity, string $entityClassName): bool;
        
        public function canUpdate(mixed $entity, string $entityClassName): bool;

        public function canDelete(mixed $entity, string $entityClassName): bool;
    }