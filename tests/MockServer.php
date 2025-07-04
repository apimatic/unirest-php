<?php

namespace Unirest\Test;

class MockServer
{
    private static $pid;

    public static function start(): void
    {
        echo "Checking if mock server is already running on http://localhost:8000 ...\n";

        // Check if something is already listening on port 8000
        if (self::isServerRunning()) {
            echo "Mock server is already running.\n";
            return; // nothing to do!
        }

        echo "Starting mock server...\n";

        $serverScript = realpath(__DIR__ . '/mock-server.php');
        $logFile = __DIR__ . '/server.log';

        if ($serverScript === false) {  // Explicit check for false
            throw new \RuntimeException("Could not resolve mock-server.php. Check the path!");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: start in background
            $cmd = "start /B php -S localhost:8000 {$serverScript} > {$logFile} 2>&1";
            pclose(popen($cmd, 'r'));
            echo "Mock server started on Windows.\n";
        } else {
            // Unix/Linux/macOS: start in background & detach
            $cmd = "php -S localhost:8000 {$serverScript} > {$logFile} 2>&1 &";
            exec($cmd);
            echo "Mock server started on Unix/Linux.\n";
        }

        sleep(1); // give the server time to start

        if (!self::isServerRunning()) {
            throw new \RuntimeException("Failed to start mock server. See server.log for details.");
        }

        echo "Mock server is up and running!\n";
    }

    private static function isServerRunning(): bool
    {
        $host = 'localhost';
        $port = 8000;
        $timeout = 1; // 1 second timeout

        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp !== false) {  // Explicit check for false
            fclose($fp);
            return true; // port open → server running
        }
        return false; // no server
    }

    public static function stop(): void
    {
        echo "Stopping mock server...\n";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Kill all processes using port 8000
            exec('for /f "tokens=5" %a in (\'netstat -ano ^| find ":8000"\') do taskkill /F /PID %a > NUL 2>&1');
        } else {
            // Kill process using port 8000 on Unix/Linux
            exec('fuser -k 8000/tcp 2>/dev/null');
        }

        echo "Mock server stopped.\n";
    }
}
