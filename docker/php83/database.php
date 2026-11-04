<?php
/**
 * Database settings for the local PHP 8.3 container only.
 * Mounted over app/config/database.php by compose.yaml.
 */
class DATABASE_CONFIG {

	var $default = array(
		'driver' => 'mysqli',
		'persistent' => false,
		'host' => 'db',
		'login' => 'croogo',
		'password' => 'croogo',
		'database' => 'croogo',
		'prefix' => '',
		'encoding' => 'utf8',
	);
}
