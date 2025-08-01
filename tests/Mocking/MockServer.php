<?php

namespace Unirest\Test\Mocking;

use RuntimeException;

class MockServer
{
    public static function start(): void
    {
        if (self::isServerRunning()) {
            echo "Mock server is already running.\n";
            return;
        }

        $serverScript = realpath(__DIR__ . '/mock-server.php');

        if ($serverScript === false) {
            throw new RuntimeException("Could not resolve mock-server.php. Check the path!");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "start /B php -S localhost:8000 $serverScript > NUL 2>&1";
            pclose(popen($cmd, 'r'));
        } else {
            $cmd = "php -S localhost:8000 $serverScript > /dev/null 2>&1 &";
            exec($cmd);
        }

        sleep(1); // give the server time to start

        if (!self::isServerRunning()) {
            throw new RuntimeException("Failed to start mock server. See server.log for details.");
        }
    }

    private static function isServerRunning(): bool
    {
        $fp = @fsockopen('localhost', 8000, $error_code, $error_message, 1);
        if ($fp !== false) {
            fclose($fp);
            return true;
        }
        return false;
    }

    public static function stop(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Kill all processes using port 8000
            exec('for /f "tokens=5" %a in (\'netstat -ano ^| find ":8000"\') do taskkill /F /PID %a > NUL 2>&1');
        } else {
            // Kill process using port 8000 on Unix/Linux
            exec('fuser -k 8000/tcp 2>/dev/null');
        }
    }
}
