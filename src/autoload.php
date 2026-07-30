<?php
declare(strict_types=1);

/**
 * PSR-4 autoloader for the RickAndMorty\ namespace, rooted at this directory.
 * Hand-rolled so the project stays install-free (no Composer, no vendor/).
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'RickAndMorty\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
