<?php

    namespace Hudsxn\IocCore\Predefined;

    use Hudsxn\IocCore\Provider\ConfigurationProvider;
    use InvalidArgumentException;
    use PDO;
    use PDOException;
    use Hudsxn\IocCore\Contracts\IDatabaseProvider;

    class PDODb implements IDatabaseProvider
    {
        private $pdo;

        public function __construct()
        {
            $details = $this->getConnectionDetails();
            $dsn = "mysql:host={$details['host']};dbname={$details['dbname']};charset={$details['charset']}";
            $username = $details['username'];
            $password = $details['password'];

            try {
                $this->pdo = new PDO( $dsn, $username, $password );
                $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            } catch ( PDOException $e ) {
                throw new \Exception( "Database connection failed: " . $e->getMessage());
            }
        }

        public function getConnectionDetails(): array
        {
            
            $configuration = ConfigurationProvider::getConfiguration();

            return [
                'host' => $configuration->get('mysql_host'),
                'username' => $configuration->get('mysql_user'),
                'charset' => $configuration->get('mysql_charset') ?? 'utf8', 
                'password' => $configuration->get('mysql_password'),
                'dbname' => $configuration->get('mysql_db')
            ];
        }

        public function getTotal(string $table): int
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM {$table}");
            $stmt->execute();

            return $stmt->fetchAll( PDO::FETCH_ASSOC )[0]['total'];
        }

        public function where( string $table, array | string $conditions, int $start = 0, int $limit = 20, string $order = 'ASC', string $orderBy = '1' ): array
        {
            $sql = "SELECT * FROM {$table} ";
            $params = [];

            if (is_array($conditions) and count($conditions) != 0) {
                $sql .= "WHERE ";
            } else {
                if (is_string($conditions) and strlen($conditions) != 0) {
                    $sql .= "WHERE ";
                }
            }

            if ( is_array( $conditions )) {
                $whereClauses = [];
                foreach ( $conditions as $field => $condition ) {
                    $operator = $condition['operator'] ?? '=';
                    $value = $condition['value'];
                    $whereClauses[] = "{$field} {$operator} ?";
                    $params[] = $value;
                }
                $sql .= implode( ' AND ', $whereClauses );
            } else {
                $sql .= $conditions;
                $params = [];
            }

            $sql .= " LIMIT {$start}, {$limit}";
            $stmt = $this->pdo->prepare( $sql );
            $stmt->execute( $params );
            
            return $stmt->fetchAll( PDO::FETCH_ASSOC );
        }

        public function insert( string $table, array $data ): int
        {
            $fields = implode( ',', array_keys( $data ));
            $placeholders = implode( ',', array_fill( 0, count( $data ), '?' ));
            $sql = "INSERT INTO {$table} ( {$fields} ) VALUES ( {$placeholders} )";

            $stmt = $this->pdo->prepare( $sql );
            $stmt->execute( array_values( $data ));

            return $this->pdo->lastInsertId();
        }

        public function delete( string $table, array $where ): bool
        {
            $whereClauses = [];
            foreach ( $where as $field => $value ) {
                $whereClauses[] = "{$field} = ?";
                $params[] = $value;
            }

            $sql = "DELETE FROM {$table} WHERE " . implode( ' AND ', $whereClauses );
            
            $stmt = $this->pdo->prepare( $sql );
            return $stmt->execute( $params );
        }

        public function update( string $table, array $update, array $where ): bool
        {
            $updateClauses = [];
            foreach ( $update as $field => $value ) {
                $updateClauses[] = "{$field} = ?";
                $updateParams[] = $value;
            }

            $whereClauses = [];
            foreach ( $where as $field => $value ) {
                $whereClauses[] = "{$field} = ?";
                $whereParams[] = $value;
            }

            $sql = "UPDATE {$table} SET " . implode( ', ', $updateClauses ) . " WHERE " . implode( ' AND ', $whereClauses );
            
            $stmt = $this->pdo->prepare( $sql );
            return $stmt->execute( array_merge( $updateParams, $whereParams ));
        }

        public function createTable( string $table, array $columns ): bool
        {
            if (count($columns) == 0) {
                throw new InvalidArgumentException("Create table {$table} has 0 columns");
            }
            $columnDefinitions = [];

            $replaceTypes = [
                'string' => 'VARCHAR(255)',
                'int' => 'INT(11)',
                'float' => 'DECIMAL(11,4)'
            ];

            foreach ( $columns as $idx => $column ) {

                if ( isset ($replaceTypes[$column['type']]) ) {
                    $column['type'] = $replaceTypes[$column['type']];
                }

                $definition = "`{$column['name']}` {$column['type']}";
                if ( isset( $column['length'] )) {
                    $definition .= "( {$column['length']} )";
                }
                if ( !empty( $column['nullable'] )) {
                    $definition .= " NULL";
                } else {
                    $definition .= " NOT NULL";
                }

                if ($idx == 0) {
                    $definition .= ' AUTO_INCREMENT';
                } else {
                    if ( isset( $column['default'] )) {
                        $definition .= " DEFAULT {$column['default']}";
                    }
                    if ( !empty( $column['unique'] )) {
                        $definition .= " UNIQUE";
                    }
                }
                
                $columnDefinitions[] = $definition;
            }

            $sql = "CREATE TABLE IF NOT EXISTS {$table}  ( " . implode( ', ', $columnDefinitions ) . ", PRIMARY KEY(`{$columns[0]['name']}`) )";

            // die('<pre>' . $sql);

            return $this->pdo->exec( $sql ) !== false;
        }

        public function dropTable( string $table )
        {
            $sql = "DROP TABLE IF EXISTS {$table}";

            return $this->pdo->exec( $sql ) !== false;
        }
    }
