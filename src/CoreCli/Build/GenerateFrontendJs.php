<?php

    namespace Hudsxn\IocCore\CoreCli\Build;
    
    use Hudsxn\IocCore\Attribute\CliController;
    use Hudsxn\IocCore\Attribute\CliMethod;
    use Hudsxn\IocCore\Attribute\Controller;
    use Hudsxn\IocCore\Attribute\Route;
    use Hudsxn\IocCore\Internals\ReflectionMap;
    use Hudsxn\IocCore\Attribute\Entity;
    use Hudsxn\IocCore\Attribute\Service;
    
    use ReflectionAttribute;
    use ReflectionMethod;
    use ReflectionClass;

    #[CliController("@build")]
    class GenerateFrontendJs
    {

        private ReflectionMap $map;

        public function __construct( ReflectionMap $map )
        {
            $this->map = $map;
        }

        #[CliMethod("generate-types")]
        public function GenerateTypes(string $sourceType, string $outputDir)
        {

            if ( ! file_exists( $outputDir )) {
                mkdir( $outputDir, 0755, true );
            }

            if ($sourceType === 'ts') {
                $typesFile = $outputDir . '/types.ts';

                $types = [];

                foreach(
                    $this->map->getClassesWithAnnotation( Entity::class )
                    as $entity
                )
                {

                    $types[] = $this->entityToType($entity);

                }

                file_put_contents($typesFile, implode("\n\n", $types));

            }



            foreach( 
                $this->map->getClassesWithAnnotation( Controller::class ) 
                as $controllerDefinition
            ) {

                // check if it has a base URL and a service. 
                // if it has a service, create methods relevant to crud. 

                $controllerAttribute = $controllerDefinition->getAttributes( Controller::class )[ 0 ]; // we know it exists from the above.

                $baseUrl          = $controllerAttribute->getArguments()[ 0 ] ?? null;
                $serviceClassName = $controllerAttribute->getArguments()[ 1 ] ?? null;

                $dirName = dirname($outputDir . '/' . $baseUrl);
                $baseName = basename($baseUrl);

                // if $baseUrl = '/v1/user', the output dir would be $outputDir . '/v1/user'

                if ( ! file_exists($dirName) ) {
                    mkdir ( $dirName, 0755, true );
                }

                $fileName = $dirName . '/' . $baseName . '.' . $sourceType;

                $sourceMethods = [];
                $requiredImports = [];

                foreach ( $controllerDefinition->getMethods() as $method ) {

                    $routeAttribute = $method->getAttributes( Route::class );

                    if ( count( $routeAttribute ) != 0 ) {
                        // its an endpoint, lets add a method.
                        
                        foreach($method->getParameters() as $param) {

                            if (str_starts_with($param->getName(), '__')) {
                                continue;
                            }
                            if (stristr($param->getType(), '\\')) {
                                if (! in_array(basename($param->getType()), $requiredImports)) {
                                    $requiredImports[] = basename($param->getType());
                                }
                            }
                        }

                        $sourceMethods[$method->getName()] = $this->methodToSource($controllerDefinition, $routeAttribute[0], $method, $sourceType === 'ts', $baseUrl);
                    }

                }

                if ($serviceClassName) {
                    // possible autowiring happening here.
                    $serviceDefinition = $this->map->getClass($serviceClassName);

                    $serviceAttribute = $serviceDefinition->getAttributes( Service::class );

                    if (count($serviceAttribute) != 0) {
                        // means it is a proper service.

                        $entity = $serviceAttribute[0]->getArguments()[0] ?? null;

                        if ($entity) {
                            // autowiring exists, 
                            
                            $sourceMethods = array_merge(array_values($sourceMethods), $this->createAutoCrudMethods($baseUrl, $entity, $sourceType === 'ts'));

                            if (! in_array(basename($entity), $requiredImports)) {
                                $requiredImports[] = basename($entity);
                            }
                        }

                    }

                }

                if ($sourceType === 'ts') {

                    // now generate imports, this needs to reference the types.ts file
                    // generated prior, 
                    $importFile = str_replace('.ts', '', $this->getRelativePath($fileName, $outputDir . '/types.ts'));

                    $generated = implode("\n\n", array_values($sourceMethods));

                    if (count($requiredImports) != 0) {
                        $generated = "import { " . implode(", ", $requiredImports) . " } from './{$importFile}';\n\n{$generated}";
                    }

                    file_put_contents($fileName, $generated);
                    
                } else {
                    file_put_contents($fileName, implode("\n\n", array_values($sourceMethods)));
                }

            }
            
        }

        private function createAutoCrudMethods(string $baseUrl, string $entityClassName, bool $isTypeScript): array
        {

            $baseEntity = basename($entityClassName);

            $getMethod = [];

            // first, the GET. 
            $getMethod[] = 'export const getOne' . $baseEntity . ' = async (id' . ($isTypeScript ? ': number' : '') . ')' . ($isTypeScript ? ': Promise<' . $baseEntity . '>' : '') . '=> {';
            $getMethod[] = "\treturn new Promise" . ($isTypeScript ? "<{$baseEntity}>": "") . "((resolve, reject) => {";
            $getMethod[] = "\t\tfetch(`" . ($baseUrl ? $baseUrl : '') . "/one/\${id}`)";
            $getMethod[] = "\t\t\t.then((resp" . ($isTypeScript ? ": Response" : "") . ") => {";
            $getMethod[] = "\t\t\t\tresp.json().then((result" . ($isTypeScript ? ": {success: boolean, data: {$baseEntity}, reason?: string}" : "") . ") => {";
            $getMethod[] = "\t\t\t\t\tif (result.success) resolve(result.data);";
            $getMethod[] = "\t\t\t\t\telse reject(result.reason)";
            $getMethod[] = "\t\t\t\t}).catch(reject)";
            $getMethod[] = "\t\t\t}).catch(reject)";
            $getMethod[] = "\t})";
            $getMethod[] = '}';


            $putMethod = [];
            $putMethod[] = 'export const create' . $baseEntity . ' = async (entity' . ($isTypeScript ? ': ' . $baseEntity : '') . ')' . 
                            ($isTypeScript ? ': Promise<' . $baseEntity . '>': '') . ' => {';
            
            $putMethod[] = "\treturn new Promise" . ($isTypeScript ? "<{$baseEntity}>" : "") . '((resolve, reject) => {';
            $putMethod[] = "\t\tfetch(`" . ($baseUrl ? $baseUrl : '') . "`, {";
            $putMethod[] = "\t\t\tmethod: 'PUT',";
            $putMethod[] = "\t\t\theaders: {";
            $putMethod[] = "\t\t\t\t'Content-Type': 'application/json'";
            $putMethod[] = "\t\t\t},";
            $putMethod[] = "\t\t\tbody: JSON.stringify(entity)";
            $putMethod[] = "\t\t})";
            $putMethod[] = "\t\t.then((resp" . ($isTypeScript ? ": Response" : "") . ") => {";
            $putMethod[] = "\t\t\tresp.json().then((result" . ($isTypeScript ? ": {success: boolean, data: {$baseEntity}, reason?: string}" : "") . ") => {";
            $putMethod[] = "\t\t\t\tif (result.success) resolve(result.data);";
            $putMethod[] = "\t\t\t\telse reject(result.reason)";
            $putMethod[] = "\t\t\t}).catch(reject)";
            $putMethod[] = "\t\t}).catch(reject)";
            $putMethod[] = "\t})";
            $putMethod[] = '}';

            $patchMethod = [];
            $patchMethod[] = 'export const update' . $baseEntity . ' = async (entity' . ($isTypeScript ? ': ' . $baseEntity : '') . ')' . 
                            ($isTypeScript ? ': Promise<' . $baseEntity . '>': '') . ' => {';
            
            $patchMethod[] = "\treturn new Promise" . ($isTypeScript ? "<{$baseEntity}>" : "") . '((resolve, reject) => {';
            $patchMethod[] = "\t\tfetch(`" . ($baseUrl ? $baseUrl : '') . "/\${entity.Id}`, {";
            $patchMethod[] = "\t\t\tmethod: 'PATCH',";
            $patchMethod[] = "\t\t\theaders: {";
            $patchMethod[] = "\t\t\t\t'Content-Type': 'application/json'";
            $patchMethod[] = "\t\t\t},";
            $patchMethod[] = "\t\t\tbody: JSON.stringify(entity)";
            $patchMethod[] = "\t\t})";
            $patchMethod[] = "\t\t.then((resp" . ($isTypeScript ? ": Response" : "") . ") => {";
            $patchMethod[] = "\t\t\tresp.json().then((result" . ($isTypeScript ? ": {success: boolean, data: {$baseEntity}, reason?: string}" : "") . ") => {";
            $patchMethod[] = "\t\t\t\tif (result.success) resolve(result.data);";
            $patchMethod[] = "\t\t\t\telse reject(result.reason)";
            $patchMethod[] = "\t\t\t}).catch(reject)";
            $patchMethod[] = "\t\t}).catch(reject)";
            $patchMethod[] = "\t})";
            $patchMethod[] = '}';

            $deleteMethod = [];
            $deleteMethod[] = 'export const delete' . $baseEntity . ' = async (entity' . ($isTypeScript ? ': ' . $baseEntity : '') . ')' . 
                            ($isTypeScript ? ': Promise<' . $baseEntity . '>': '') . ' => {';
            
            $deleteMethod[] = "\treturn new Promise" . ($isTypeScript ? "<{$baseEntity}>" : "") . '((resolve, reject) => {';
            $deleteMethod[] = "\t\tfetch(`" . ($baseUrl ? $baseUrl : '') . "/\${entity.Id}`, {";
            $deleteMethod[] = "\t\t\tmethod: 'DELETE'";
            $deleteMethod[] = "\t\t})";
            $deleteMethod[] = "\t\t.then((resp" . ($isTypeScript ? ": Response" : "") . ") => {";
            $deleteMethod[] = "\t\t\tresp.json().then((result" . ($isTypeScript ? ": {success: boolean, data: {$baseEntity}, reason?: string}" : "") . ") => {";
            $deleteMethod[] = "\t\t\t\tif (result.success) resolve(result.data);";
            $deleteMethod[] = "\t\t\t\telse reject(result.reason)";
            $deleteMethod[] = "\t\t\t}).catch(reject)";
            $deleteMethod[] = "\t\t}).catch(reject)";
            $deleteMethod[] = "\t})";
            $deleteMethod[] = '}';

            // now for the create method

            $listMethod = [];

            $listMethod[] = 'export const get' . $baseEntity . 'Collection = async (where' . 
                                ($isTypeScript ? ": Record<keyof $baseEntity , { operator: string, value: any }>" : '') . 
                                ', start' . ($isTypeScript ? ': number' : '') . ' = 0' . 
                                ', limit' . ($isTypeScript ? ': number' : '') . ' = 20' .
                                ', order' . ($isTypeScript ? ': \'ASC\' | \'DESC\'' : '') . ' = \'ASC\'' . 
                                ', orderBy' . ($isTypeScript ? ': keyof ' . $baseEntity . ' | \'1\'' : '') . ' = \'1\'' . 
                                ')' . ($isTypeScript ? ": Promise<{$baseEntity}[]>" : '') . '=> {';
            
            $listMethod[] = "\treturn new Promise" . ($isTypeScript ? "<{$baseEntity}[]>" : "") . "((resolve, reject) => {";

            $listMethod[] = "\t\tfetch(`" . ($baseUrl ? $baseUrl : '') . "`, {";
            $listMethod[] = "\t\t\tmethod: 'POST',";
            $listMethod[] = "\t\t\theaders: {";
            $listMethod[] = "\t\t\t\t'Content-Type': 'application/json'";
            $listMethod[] = "\t\t\t},";
            $listMethod[] = "\t\t\tbody: JSON.stringify({ where, start, limit, order, orderBy })";
            $listMethod[] = "\t\t})";
            $listMethod[] = "\t\t.then((resp" . ($isTypeScript ? ': Response' : '') . ") => {";
            $listMethod[] = "\t\t\tresp.json().then((result" . ($isTypeScript ? ": { success: boolean, data: {$baseEntity}[], reason?: string }" : "") . ") => {";
            $listMethod[] = "\t\t\t\treturn result.success ? resolve(result.data) : reject(result.reason)";
            $listMethod[] = "\t\t\t}).catch(reject)";
            $listMethod[] = "\t\t}).catch(reject)";
            $listMethod[] = "});";
            

            $listMethod[] = '}';

            return [
                implode("\n", $getMethod),
                implode("\n", $listMethod),
                implode("\n", $putMethod),
                implode("\n", $patchMethod),
                implode("\n", $deleteMethod)
            ];
        }

        private function getRelativePath($from, $to) {
            $from = explode(DIRECTORY_SEPARATOR, realpath($from));
            $to = explode(DIRECTORY_SEPARATOR, realpath($to));
            
            while (isset($from[0]) && isset($to[0]) && $from[0] === $to[0]) {
                array_shift($from);
                array_shift($to);
            }
        
            $result = str_repeat('..' . '/', count($from) - 1) . implode('/', $to);

            if (!stristr($result, '../')) {
                return './' . implode('/', $to);
            }
            return $result;
        }

        private function entityToType($entityClass): string
        {
            $replaceTypes = [
                'int' => 'number',
                'float' => 'number',
                'bool' => 'boolean'
            ];
            $source = [];

            $source[] = '/** Auto-Generated by Hudsxn\IocCore **/';
            $source[] = 'export type ' . basename($entityClass->getName()) . ' = {';

            foreach($entityClass->getProperties() as $idx => $property) {
                $type = basename(strval($property->getType()));
                $type = str_replace('?', '', $type);
                $type = $replaceTypes[$type] ?? $type;

                $source[] = "\t{$property->getName()}?: {$type};";
            }

            $source[] = '};';

            return implode("\n", $source);
        }

        private function methodToSource(ReflectionClass $class, ReflectionAttribute $route, ReflectionMethod $method, bool $isTypeScript, string $baseUrl): string
        {
            $httpMethod = $route->getArguments()[0] ?? null;
            $path       = $route->getArguments()[1] ?? null;
            $produces   = $route->getArguments()[2] ?? 'application/json';
            $consumes   = $route->getArguments()[3] ?? 'application/json';

            if ( !$httpMethod || !$path ) {
                throw new \Exception('Missing required route arguments');
            }

            $source = [
                '/**' ,
                '* Auto-Generated method by Hudsxn\IocCore',
            ];

            foreach( $method->getParameters() as $parameter ) {
                $type = basename(str_replace('\\', '/', $parameter->getType()));
                $source[] = "* @param {$type} {$parameter->getName()}";
            }

            $source[] = '**/';

            // now the doc is done, lets move onto the actual definition

            $definitionLine = 'export const ' . $method->getName() . ' = async (';
            $replaceTypes = [
                'int' => 'number',
                'float' => 'number'
            ];

            foreach( $method->getParameters() as $pos => $parameter ) {

                $type = basename(strval($parameter->getType()));

                if ( isset( $replaceTypes[ $type ] ) ) {
                    $type = $replaceTypes[ $type ];
                }

                $definitionLine .= $pos > 0 ? ', ' : '';

                $definitionLine .= $parameter->getName();

                if ( $isTypeScript ) {
                    $definitionLine .= ": {$type}";
                }

            }

            $definitionLine .= ")";

            if ($isTypeScript) {
                // use any for now until a good way to detect this works (remember; imports.)
                $definitionLine .= ": Promise<any>";
            }
            $definitionLine .= " => {";

            $source[] = $definitionLine;
            $source[] = "\treturn new Promise((resolve, reject) => {";

            unset($definitionLine);

            // the contents here.
            // anything in the body needs tab indentation.

            $source[] = "\t\tfetch('" . ($baseUrl ? $baseUrl . '/' : '') . "{$path}', {";
            $source[] = "\t\t\tmethod: '{$httpMethod}',";
            $source[] = "\t\t\theaders: {";
            $source[] = "\t\t\t\t'Content-Type': '{$consumes}'";
            $source[] = "\t\t\t},";

            $bodyLine = "\t\t\tbody: JSON.stringify({ ";

            foreach($method->getParameters() as $idx => $param) {
                $bodyLine .= ($idx > 0 ? ", " : "");
                $bodyLine .= $param->getName();
            }

            $source[] = "$bodyLine })";
            $source[] = "\t\t})";

            unset($bodyLine);

            // now for the .then(resp => resp.json() stuff.)

            $source[] = "\t\t.then((resp" . ($isTypeScript ? ": Response" : "") . ") => {";
            $source[] = "\t\t\tresp.json().then((result" . ($isTypeScript ? ": any" : "") . ") => {";
            $source[] = "\t\t\t\tif ( typeof result.success === 'boolean' ){ ";
            $source[] = "\t\t\t\t\tif (!result.success) {";
            $source[] = "\t\t\t\t\t\treject(result.reason ?? result.error);";
            $source[] = "\t\t\t\t\t} else {";
            $source[] = "\t\t\t\t\t\tresolve(result.data ?? result.result ?? result.response);";
            $source[] = "\t\t\t\t\t}";
            $source[] = "\t\t\t\t} else {";
            $source[] = "\t\t\t\t\tresolve(result);";
            $source[] = "\t\t\t\t}";
            $source[] = "\t\t\t}).catch(reject)";
            $source[] = "\t\t}).catch(reject)";
            $source[] = "\t});";

            $source[] = "};";

            return implode("\n", $source);
        }
    }