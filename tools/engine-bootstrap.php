<?php

// Pemuat ringan untuk menjalankan mesin game TANPA Laravel/Composer
// (dipakai oleh tools/*.php dan CI sebelum composer install).

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Game\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/../app/Game/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

function engine_rules(int $version = 1): App\Game\Rules
{
    return App\Game\Rules::load(__DIR__.'/../resources/game', $version);
}
