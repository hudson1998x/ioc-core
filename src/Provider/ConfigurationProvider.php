<?php

    namespace Hudsxn\IocCore\Provider;

    class ConfigurationProvider
    {
        private array $configurationData = [];

        private static ?self $instance = null;

        private function __construct()
        {
            self::$instance = $this;

            foreach ( $_ENV as $k => $v ) {
                $this->set( $k, $v );
            }

            if ( file_exists( ".env" ) ) {
                foreach ( parse_ini_file(".env") as $k => $v ) {
                    $this->set( $k, $v );
                }
            }

        }

        public function set( string $key, mixed $value ): self
        {
            $this->configurationData[ $key ] = $value;
            return $this;
        }

        public function get( string $key ): mixed
        {
            return $this->configurationData[ $key ] ?? null;
        }

        public static function getConfiguration(): self
        {
            return self::$instance ?? new self();
        }
    }