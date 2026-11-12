<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Reads a setting from Croogo's settings table, such as Site.title.
 *
 * One value doesn't need an entity: Doctrine's DBAL layer runs the query
 * directly, with the value passed as a parameter. Croogo also writes these
 * settings to app/config/settings.yml for itself, but the table is where its
 * admin saves them first.
 */
final class CroogoSettings
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $key): ?string
    {
        $value = $this->connection->fetchOne('SELECT value FROM settings WHERE `key` = ?', [$key]);

        return is_string($value) ? $value : null;
    }
}
