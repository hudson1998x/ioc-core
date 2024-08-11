<?php

    namespace Hudsxn\IocCore\Objects;

    class Response
    {
        private int $statusCode = 200;

        private string $contentType = 'text/html';

        private string $outputBuffer = '';

        public function __construct(string $output, string $contentType = 'text/html',  int $statusCode = 200)
        {
            $this->outputBuffer = $output;
            $this->contentType = $contentType;
            $this->statusCode = $statusCode;
        }

        public function __toString(): string
        {
            http_response_code($this->statusCode);
            header("Content-Type: {$this->contentType}");

            return $this->outputBuffer;
        }
    }