<?php
/**
 * Exercise the plugin's early return without loading WordPress or dependencies.
 * Run this script with PHP 8.1 and PHP 8.2+ to test both sides of the PHP guard.
 *
 * Usage: php tests/regression/minimum-php-bootstrap.php
 */

$root = dirname(__DIR__, 2);

if (isset($argv[1]) && $argv[1] === '--probe') {
    $wp_version = $argv[2];
    $vendor = sys_get_temp_dir() . '/planner-bootstrap-' . uniqid('', true);
    $protection = $vendor . '/publishpress/instance-protection';
    mkdir($protection, 0700, true);
    file_put_contents($protection . '/include.php', '<?php throw new RuntimeException("planner-runtime-reached");');
    define('PP_LIB_VENDOR_PATH', $vendor);

    $reached = false;
    try {
        require $root . '/publishpress.php';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'planner-runtime-reached') {
            throw $exception;
        }
        $reached = true;
    }

    unlink($protection . '/include.php');
    rmdir($protection);
    rmdir(dirname($protection));
    rmdir($vendor);
    echo $reached ? 'runtime-reached' : 'returned-early';
    exit;
}

foreach (['5.4.9', '5.5', '7.1'] as $wordpress) {
    $command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -d error_reporting=-1 '
        . escapeshellarg(__FILE__) . ' --probe ' . escapeshellarg($wordpress);
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the bootstrap probe.');
    }
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    $expected = PHP_VERSION_ID >= 80200 && version_compare($wordpress, '5.5', '>=')
        ? 'runtime-reached'
        : 'returned-early';
    if ($status !== 0 || $output !== $expected || $errors !== '') {
        fwrite(STDERR, 'FAIL: PHP ' . PHP_VERSION . ', WordPress ' . $wordpress . ': expected '
            . $expected . ', received ' . $output . $errors . "\n");
        exit(1);
    }
    echo 'PASS: PHP ' . PHP_VERSION . ', WordPress ' . $wordpress . ': ' . $output . "\n";
}
