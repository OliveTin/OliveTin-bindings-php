<?php

declare(strict_types=1);

namespace OliveTin\Api\Tests;

use OliveTin\Api\OliveTinApiException;
use OliveTin\Api\OliveTinClient;
use PHPUnit\Framework\TestCase;

final class OliveTinClientTest extends TestCase
{
    private ?string $routerFile = null;

    /** @var resource|null */
    private $serverProcess = null;

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
            $this->serverProcess = null;
        }

        if ($this->routerFile !== null && is_file($this->routerFile)) {
            unlink($this->routerFile);
            $this->routerFile = null;
        }
    }

    public function testConstructorRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OliveTinClient('https://example.test', '');
    }

    public function testInitSucceedsAgainstMockServer(): void
    {
        $baseUrl = $this->startMockServer(200, '{"showFooter":true}');
        $client = new OliveTinClient($baseUrl, 'test-token');

        $result = $client->init();

        self::assertSame(['showFooter' => true], $result);
    }

    public function testStartActionAndWaitReturnsLogEntry(): void
    {
        $baseUrl = $this->startMockServer(200, '{"logEntry":{"output":"ok","executionTrackingId":"e1"}}');
        $client = new OliveTinClient($baseUrl, 'test-token');

        $log = $client->startActionAndWait('action-1', ['msg' => 'hi']);

        self::assertSame('ok', $log['output']);
        self::assertSame('e1', $log['executionTrackingId']);
    }

    public function testHttpErrorRaisesOliveTinApiException(): void
    {
        $baseUrl = $this->startMockServer(401, '{"code":"unauthenticated","message":"bad token"}');
        $client = new OliveTinClient($baseUrl, 'bad-token');

        try {
            $client->init();
            self::fail('Expected OliveTinApiException');
        } catch (OliveTinApiException $e) {
            self::assertSame(401, $e->httpStatus());
            self::assertSame('unauthenticated', $e->connectCode());
            self::assertSame('bad token', $e->getMessage());
        }
    }

    public function testCurlTransportFailureRaisesOliveTinApiException(): void
    {
        $client = new OliveTinClient('http://127.0.0.1:9', 'test-token');

        $this->expectException(OliveTinApiException::class);
        $this->expectExceptionMessageMatches('/cURL error:/');
        $client->init();
    }

    private function startMockServer(int $status, string $jsonBody): string
    {
        $this->routerFile = tempnam(sys_get_temp_dir(), 'ot-router-');
        if ($this->routerFile === false) {
            self::fail('Unable to create temporary router script');
        }

        $router = $this->routerFile . '.php';
        rename($this->routerFile, $router);
        $this->routerFile = $router;

        $statusLiteral = (string) $status;
        $bodyLiteral = var_export($jsonBody, true);
        file_put_contents($this->routerFile, <<<PHP
<?php
http_response_code({$statusLiteral});
header('Content-Type: application/json');
echo {$bodyLiteral};
PHP);

        $port = $this->allocatePort();
        $cmd = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $port,
            $this->routerFile,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . '/ot-php-server.out', 'a'],
            2 => ['file', sys_get_temp_dir() . '/ot-php-server.err', 'a'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, sys_get_temp_dir());
        if (!is_resource($process)) {
            self::fail('Unable to start mock HTTP server');
        }
        $this->serverProcess = $process;
        fclose($pipes[0]);

        $this->waitForServer($port);

        return 'http://127.0.0.1:' . $port;
    }

    private function allocatePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::fail('Unable to allocate ephemeral port: ' . $errstr);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($name === false || !str_contains($name, ':')) {
            self::fail('Unable to determine allocated port');
        }

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitForServer(int $port): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if (is_resource($conn)) {
                fclose($conn);

                return;
            }
            usleep(20_000);
        }

        self::fail('Mock HTTP server did not become ready on port ' . $port);
    }
}
