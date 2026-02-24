<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rutas de Binarios
    |--------------------------------------------------------------------------
    | Aquí definimos dónde están instalados Node y NPM en el sistema.
    */
    'node_path' => env('BROWSERSHOT_NODE_PATH', '/usr/bin/node'),
    'npm_path'  => env('BROWSERSHOT_NPM_PATH', '/usr/bin/npm'),
];
