<?php

// CakePHP 1.3 has no autoloader and defines its paths and helper functions
// (__(), h(), env() and friends) at runtime. PHPStan needs them declared
// before it reads the code, so this file sets them up the way
// app/webroot/index.php and cake/bootstrap.php do, without dispatching.

define('DS', '/');
define('ROOT', '/app');
define('APP_DIR', 'app');
define('WEBROOT_DIR', 'webroot');
define('WWW_ROOT', ROOT . DS . APP_DIR . DS . WEBROOT_DIR . DS);
define('CAKE_CORE_INCLUDE_PATH', ROOT);
define('CORE_PATH', ROOT . DS);
define('APP_PATH', ROOT . DS . APP_DIR . DS);

define('PHP5', true);

require CORE_PATH . 'cake' . DS . 'basics.php';
require CORE_PATH . 'cake' . DS . 'config' . DS . 'paths.php';
