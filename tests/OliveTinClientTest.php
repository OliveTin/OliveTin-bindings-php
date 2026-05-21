<?php

declare(strict_types=1);

namespace OliveTin\Api\Tests;

use OliveTin\Api\OliveTinClient;
use PHPUnit\Framework\TestCase;

final class OliveTinClientTest extends TestCase
{
    public function testConstructorRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OliveTinClient('https://example.test', '');
    }
}
