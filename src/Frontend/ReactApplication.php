<?php

    namespace Hudsxn\IocCore\Frontend;

    class ReactApplication
    {
        
        private static array $selfs = [];

        private string $baseUrl;

        private string $jsFile;

        private string $cssFile;

        private array $servableDirectories = [];

        public function __construct( string $baseUrl, string $jsFile, string $cssFile )
        {
            $this->baseUrl = $baseUrl;
            $this->jsFile = $jsFile;
            $this->cssFile = $cssFile;

            self::$selfs[ $this->baseUrl ] = $this;
        }

        public function withPublicDirectory( string $publicDir ): self
        {
            $this->servableDirectories[] = $publicDir;
            return $this;
        }

        public function handleRequest( string $method, string $path ) : void
        {
            foreach ( $this->servableDirectories as $pubDir )
            {
                if ( file_exists( $pubDir . '/' . $path ) && ! is_dir( $pubDir . '/' . $path )) {
                    $this->serveFile( $pubDir . '/' . $path );
                    exit();
                }       
            }
            if ( (str_starts_with( $path, $this->baseUrl ) && $this->baseUrl != '/') || $this->baseUrl == '/' && $path == '/') {
                $this->servePage();
                exit();
            }
        }

        public function servePage() 
        {
            exit('
                <html>
                    <head>
                        <meta name="viewport" content="initial-scale=1.0,width=device-width"/>
                    </head>
                    <body>
                        <div id="app"></div>
                        <script src="/' . $this->jsFile . '"></script>
                        <link rel="stylesheet" href="/' . $this->cssFile . '">
                    </body>
                </html>
            ');
        }

        private function serveFile($path) 
        {
            $extName = pathinfo($path, PATHINFO_EXTENSION);
            $baseType = "";

            switch ($extName)
            {
                case 'png':
                case 'gif':
                case 'jpeg':
                case 'jpg':
                case 'webp':
                case 'avif':
                case 'bmp':
                    $baseType = "image";
                break;
                case 'js':
                case 'css':
                case 'html':
                case 'txt':
                    $baseType = "text";
                    if ($extName == 'txt') {
                        $extName = 'plain';
                    }
                break;
                case 'mp4':
                case 'ogg':
                case 'mov':
                    $baseType = 'video';
                break;
                case 'json':
                case 'pdf':
                    $baseType = 'application';
                break;
            }

            header("Content-Type: {$baseType}/{$extName}");

            readfile($path);
        }
        public static function withApplication( string $baseUrl, string $jsFile, string $cssFile, array $publicDirs = [] )
        {
            $app = new ReactApplication($baseUrl, $jsFile, $cssFile);
            
            foreach($publicDirs as $dir) {
                $app->withPublicDirectory($dir);
            }
        }
        public static function Serve( string $path ): void
        {
            foreach(self::$selfs as $baseUrl => $app) 
            {
                $app->handleRequest('GET', $path);
            }
        }
    }