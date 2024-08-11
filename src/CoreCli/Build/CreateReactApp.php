<?php

    namespace Hudsxn\IocCore\CoreCli\Build;
    
    use Hudsxn\IocCore\Attribute\CliController;
    use Hudsxn\IocCore\Attribute\CliMethod;

    #[CliController("@build")]
    class CreateReactApp
    {

        #[CliMethod("add-react-app")]
        public function CreateApp(string $dir, string $name, string $selector = '#app') 
        {
            $files = [
                'index.tsx' => [
                    "import { createRoot } from 'react-dom/client';",
                    "import { AppRouter } from './app/router';",
                    "import './style.css';",
                    "",
                    "const root = createRoot(document.querySelector('{$selector}') as HTMLDivElement);",
                    "",
                    "root.render(<AppRouter />);"
                ], 
                'style.css' => [
                    '/** root css file, good for imports and defining CSS vars **/'
                ],
                'app/router.tsx' => [
                    "import { BrowserRouter, Route, Routes } from 'react-router-dom'",
                    "",
                    "export const AppRouter = () => {",
                    "\treturn (",
                    "\t\t<BrowserRouter>",
                    "\t\t\t<Routes>",
                    "\t\t\t\t<Route path='/'>",
                    "",    
                    "\t\t\t\t</Route>",
                    "\t\t\t</Routes>",
                    "\t\t</BrowserRouter>",
                    "\t)",
                    "}"
                ]
            ];


            foreach($files as $relPath => $contents) {
                
                $file = $dir . '/' . $name . '/' . $relPath;

                if (stristr($file, '/')) {
                    @mkdir(dirname($file), 0755, true);
                }
                file_put_contents($file, implode("\n", $contents));
            }

            exit('Setup new react application in ' . $dir);
        }

    }