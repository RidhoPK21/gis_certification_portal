<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$compiler = $app['blade.compiler'];

$directory = new RecursiveDirectoryIterator('resources/views');
$iterator = new RecursiveIteratorIterator($directory);
$errors = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
        $path = $file->getPathname();
        $compiled = $compiler->compileString(file_get_contents($path));
        $temp = 'storage/framework/views/test_chk_' . md5($path) . '.php';
        file_put_contents($temp, $compiled);
        exec("php -l " . escapeshellarg($temp) . " 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            echo "SYNTAX ERROR in $path:\n";
            echo implode("\n", $output) . "\n";
            $errors++;
        }
        @unlink($temp);
        $output = [];
    }
}

if ($errors === 0) {
    echo "SUCCESS: All Blade views compiled and passed syntax check!\n";
} else {
    echo "FAILED: Found $errors view(s) with syntax errors.\n";
    exit(1);
}
