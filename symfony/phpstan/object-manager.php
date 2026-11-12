<?php

declare(strict_types=1);

/*
 * Hands PHPStan's Doctrine extension the app's own entity manager, so it reads
 * the same mapping as the running app: which table and columns each entity
 * uses, and their types. It boots the kernel with the container's environment
 * (APP_ENV, DATABASE_URL), as bin/console does.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
