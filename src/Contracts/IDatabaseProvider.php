<?php

    namespace Hudsxn\IocCore\Contracts;

    interface IDatabaseProvider
    {   
        /**
         * Query the database
         * @param string $table
         * @param array|string $conditions - array format: [[ field: ['operator' => '=', 'value' => 'someValue'] ]], string format = 'Id = ?'
         * @param int $start
         * @param int $limit
         * @return array
         */
        public function where(string $table, array | string $conditions, int $start = 0, int $limit = 20, string $order = 'ASC', string $orderColumn = '1'): array;
        
        public function insert(string $table, array $data): int;

        public function delete(string $table, array $where): bool;

        public function update(string $table, array $update, array $where): bool;

        /**
         * The template to create the table
         * @param string $table
         * @param array $columns [name: string, type: string, length?: number, nullable: bool, default: string | AUTO_INCREMENT | CURRENT_TIMESTAMP, unique?: bool]
         * @return bool - if it has created or not
         */
        public function createTable(string $table, array $columns): bool;

        public function dropTable(string $table);
    }