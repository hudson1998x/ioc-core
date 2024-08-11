<?php

    namespace Hudsxn\IocCore\CoreCli\Build;
    
    use Hudsxn\IocCore\Attribute\CliController;
    use Hudsxn\IocCore\Attribute\CliMethod;
    use Hudsxn\IocCore\Predefined\PDODb;

    #[CliController("@build")]
    class CreateEntity
    {

        #[CliMethod("entity")]
        public function CreateApp(string $className, ?string $dbAdapter = PDODb::class , ?string $securityProvider) 
        {
            if ( empty ( $className )) {
                die("Missing class name, use this command:\nphp cli.php @build/entity --className=App\\Your\\Class\\Name --dbAdapter=" . PDODb::class);
            } 
            
            if ( empty ( $dbAdapter )) {
                $dbAdapter = PDODb::class;
            }

            $path = 'src/' . 
                    str_replace(
                        '\\' ,
                        '/', 
                        str_replace('App\\', '', $className)
                    ) . 
                    '.php';

            $dir = dirname($path);

            @mkdir($dir, 0755, true);

            $files = [
                ($dir . '/' . basename($className) . 'Service.php') => [
                    '<?php',
                    '',
                    'namespace ' . dirname($className) .';',
                    '',
                    'use Hudsxn\IocCore\Attribute\ListenFor;',
                    'use Hudsxn\IocCore\Attribute\Service;',
                    'use ' . $dbAdapter . ';',
                    'use Hudsxn\IocCore\Traits\DynamicClass;',
                    '',
                    'use ' . $className . ';',
                    '',
                    '#[Service(' . basename($className) . '::class, ' . basename($dbAdapter) . '::class)]',
                    'class ' . basename($className) . 'Service',
                    '{',
                    "\tuse DynamicClass;",
                    "}"
                ],
                "{$path}" => [
                    "<?php",
                    "",
                    "namespace " . dirname($className) . ";",
                    "",
                    "use Hudsxn\IocCore\Attribute\Entity;",
                    "",
                    "#[Entity(\\{$dbAdapter}::class)]",
                    "class " . basename($className),
                    "{",
                    "",
                    "\tpublic int \$Id;",
                    "",
                    "}"
                ],
                ($dir . '/' . basename($className) . 'Controller.php') => [
                    '<?php',
                    '',
                    'namespace App\User\Controller;',
                    '',
                    'use ' . dirname($className) . '\\' . basename($className) . 'Service;',
                    'use ' . $className . ';',
                    '',
                    'use Hudsxn\IocCore\Attribute\Controller;',
                    'use Hudsxn\IocCore\Attribute\Route;',
                    'use Hudsxn\IocCore\Traits\DynamicClass;',
                    '',
                    '#[Controller("/", ' . basename($className) . 'Service::class)]',
                    'class ' . basename($className) . 'Controller',
                    '{',
                    "\tuse DynamicClass;",
                    '',
                    "\t#[Route(\"GET\", \"test\", \"application/json\")]",
                    "\tpublic function SomeFunction()",
                    "\t{",
                    "\t\treturn [];",
                    "\t}",
                    '}',
                ]
            ];

            foreach($files as $file => $contents) {
                file_put_contents($file, implode("\n", $contents));
            }
        }

    }