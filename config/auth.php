<?php

/*
| Dua jenis pengguna yang terpisah:
|   - fighter: pemain, masuk lewat DM Instagram (tanpa password)
|   - admin:   pengelola, masuk dengan email + password (+ TOTP, Fase 10)
*/

return [

    'defaults' => [
        'guard' => 'fighter',
        'passwords' => 'admins',
    ],

    'guards' => [
        'fighter' => [
            'driver' => 'session',
            'provider' => 'fighters',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'fighters' => [
            'driver' => 'eloquent',
            'model' => App\Models\Fighter::class,
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
