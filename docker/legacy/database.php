<?php
/**
 * Database settings for the local Docker setup only.
 * Mounted over app/config/database.php by compose.yaml.
 */
class DATABASE_CONFIG {

	var $default = array(
		'driver' => 'mysql',
		'persistent' => false,
		'host' => 'db',
		'login' => 'croogo',
		'password' => 'croogo',
		'database' => 'croogo',
		'prefix' => '',
		'encoding' => 'utf8',
	);
}
