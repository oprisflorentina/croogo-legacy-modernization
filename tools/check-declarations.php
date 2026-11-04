<?php
/**
 * Loads every class file of the app and the core, each in its own PHP
 * process, after bootstrapping CakePHP the way app/webroot/index.php does.
 * PHP checks inheritance (signatures, static vs instance) when a class is
 * declared, and those errors are fatal, so a fresh process per file is the
 * only way to collect them all in one pass.
 *
 * Usage (inside the php83 container):
 *   php tools/check-declarations.php          check everything
 *   php tools/check-declarations.php <file>   load one file (used internally)
 */

$root = dirname(__DIR__);

if (isset($argv[1])) {
    define('DS', DIRECTORY_SEPARATOR);
    define('ROOT', $root);
    define('APP_DIR', 'app');
    define('CAKE_CORE_INCLUDE_PATH', ROOT);
    define('WEBROOT_DIR', 'webroot');
    define('WWW_ROOT', ROOT . DS . 'app' . DS . 'webroot' . DS);
    define('APP_PATH', ROOT . DS . APP_DIR . DS);
    define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
    $_SERVER['HTTP_HOST'] = 'localhost';
    include CORE_PATH . 'cake' . DS . 'bootstrap.php';
    // Classes that core files expect to be loaded already.
    App::import('Core', array('Controller', 'Model', 'View', 'Helper', 'Component', 'Behavior', 'Dispatcher'));
    App::import('Controller', 'App');
    App::import('Model', 'App');
    App::import('Helper', 'App');
    $file = $argv[1];
    if (strpos($file, '/datasources/dbo/') !== false) {
        require_once LIBS . 'model' . DS . 'datasources' . DS . 'dbo_source.php';
    }
    // AppError extends the core ErrorHandler, which loads only on demand.
    if (basename($file) === 'app_error.php') {
        require_once LIBS . 'error.php';
    }
    // A plugin's classes extend its own <plugin>_app_controller / _app_model.
    if (preg_match('#/plugins/([^/]+)/#', $file, $m)) {
        foreach (array('app_controller', 'app_model') as $base) {
            $path = APP . 'plugins' . DS . $m[1] . DS . $m[1] . '_' . $base . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
    require_once $file;
    exit(0);
}

// cake/libs/{controller,model,view/helpers}/app_*.php are the defaults the
// app's own AppController/AppModel/AppHelper replace, so they never load.
// app/index.php is a front controller, not a class file.
$skip = '#/(tests|docker|tools|webroot|vendors/(shells|simpletest)|console/templates|views|layouts|elements|config|locale|tmp)/|/cake/console/|/cake/libs/(controller/app_controller|model/app_model|view/helpers/app_helper)\.php$|/app/index\.php$#';
$files = [];
foreach ([$root . '/cake', $root . '/app'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $f) {
        $p = $f->getPathname();
        if (substr($p, -4) === '.php' && !preg_match($skip, $p)) {
            $files[] = $p;
        }
    }
}
sort($files);

$failed = 0;
foreach ($files as $file) {
    $out = shell_exec('php ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($file) . ' 2>&1');
    if (preg_match('/(Fatal error|Parse error):\s*(.*?) in \/.*$/m', (string) $out, $m)) {
        $failed++;
        echo str_replace($root . '/', '', $file), "\n    ", $m[2], "\n";
    }
}
echo count($files), " files loaded, $failed with fatal errors\n";
