<?php


return [
    /*
    |--------------------------------------------------------------------------
    | JWT Secret Key
    |--------------------------------------------------------------------------
    | Esta llave se usa para firmar los tokens JWT que envuelven a Sanctum.
    */
    'secret' => env('JWT_SECRET', env('APP_KEY')),

    'algo' => 'HS256',
];
