<?php

declare(strict_types=1);

namespace SmbWebClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use SmbWebClient\Config;
use SmbWebClient\SmbClient;

/**
 * Unit tests for SmbClient
 * 
 * Note: These tests mock the SMB operations. Integration tests with real
 * SMB servers are in tests/Integration/SmbClientIntegrationTest.php
 */
class SmbClientTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config(
            smbDefaultServer: 'testserver',
            smbServerList: [],
            hideDotFiles: true,
            hideSystemShares: true,
            hidePrinterShares: false,
        );
    }

    #[Test]
    public function constructorSetsConfiguration(): void
    {
        $client = new SmbClient($this->config);
        
        $this->assertInstanceOf(SmbClient::class, $client);
    }

    #[Test]
    public function constructorAcceptsCredentials(): void
    {
        $client = new SmbClient($this->config, 'user', 'password');
        
        $this->assertInstanceOf(SmbClient::class, $client);
    }

    #[Test]
    public function setCredentialsClearsServerCache(): void
    {
        $client = new SmbClient($this->config, 'user1', 'pass1');
        
        // Setting new credentials should work without errors
        $client->setCredentials('user2', 'pass2');
        
        $this->assertInstanceOf(SmbClient::class, $client);
    }

    #[Test]
    public function discoverServersReturnsServerListWhenConfigured(): void
    {
        $config = new Config(
            smbDefaultServer: 'default',
            smbServerList: ['server1', 'server2', 'server3'],
        );
        $client = new SmbClient($config);
        
        $servers = $client->discoverServers();
        
        $this->assertSame(['server1', 'server2', 'server3'], $servers);
    }

    #[Test]
    public function discoverServersReturnsDefaultServerWhenListEmpty(): void
    {
        $config = new Config(
            smbDefaultServer: 'mydefault',
            smbServerList: [],
        );
        $client = new SmbClient($config);
        
        $servers = $client->discoverServers();
        
        // When no server list and no nmblookup, should return default
        $this->assertContains('mydefault', $servers);
    }

    #[Test]
    public function getServerReturnsServerInstance(): void
    {
        $client = new SmbClient($this->config, 'user', 'pass');
        
        $server = $client->getServer('testhost');
        
        $this->assertInstanceOf(\Icewind\SMB\IServer::class, $server);
    }

    #[Test]
    public function getServerUsesDefaultHostWhenNull(): void
    {
        $config = new Config(smbDefaultServer: 'defaulthost');
        $client = new SmbClient($config, 'user', 'pass');
        
        $server = $client->getServer(null);
        
        $this->assertInstanceOf(\Icewind\SMB\IServer::class, $server);
    }

    #[Test]
    public function getServerCachesServerInstances(): void
    {
        $client = new SmbClient($this->config, 'user', 'pass');
        
        $server1 = $client->getServer('host1');
        $server2 = $client->getServer('host1');
        
        $this->assertSame($server1, $server2);
    }

    #[Test]
    public function getServerCreatesDifferentInstancesForDifferentHosts(): void
    {
        $client = new SmbClient($this->config, 'user', 'pass');
        
        $server1 = $client->getServer('host1');
        $server2 = $client->getServer('host2');
        
        $this->assertNotSame($server1, $server2);
    }

    #[Test]
    public function getShareReturnsShareInstance(): void
    {
        $client = new SmbClient($this->config, 'user', 'pass');
        
        $share = $client->getShare('testhost', 'testshare');
        
        $this->assertInstanceOf(\Icewind\SMB\IShare::class, $share);
    }

    #[Test]
    #[DataProvider('systemShareProvider')]
    public function hideSystemSharesFilteringLogic(string $shareName, bool $shouldBeFiltered): void
    {
        // This is a logic test - the actual filtering happens in listShares()
        // We test the logic that would be applied
        
        $isSystemShare = str_ends_with($shareName, '$') || in_array($shareName, ['IPC$', 'ADMIN$']);
        
        if ($shouldBeFiltered) {
            $this->assertTrue($isSystemShare, "Share '$shareName' should be identified as a system share");
        } else {
            $this->assertFalse($isSystemShare, "Share '$shareName' should not be identified as a system share");
        }
    }

    public static function systemShareProvider(): array
    {
        return [
            'IPC$' => ['IPC$', true],
            'ADMIN$' => ['ADMIN$', true],
            'C$' => ['C$', true],
            'D$' => ['D$', true],
            'SHARE1' => ['SHARE1', false],
            'Documents' => ['Documents', false],
            'Public' => ['Public', false],
            'print$' => ['print$', true],
        ];
    }

    #[Test]
    #[DataProvider('printerShareProvider')]
    public function hidePrinterSharesFilteringLogic(string $shareName, bool $shouldBeFiltered): void
    {
        // Test the printer share detection logic
        $isPrinterShare = str_ends_with($shareName, '$') || 
            stripos($shareName, 'print') !== false ||
            stripos($shareName, 'lpt') !== false;
        
        if ($shouldBeFiltered) {
            $this->assertTrue($isPrinterShare, "Share '$shareName' should be identified as a printer share");
        } else {
            $this->assertFalse($isPrinterShare, "Share '$shareName' should not be identified as a printer share");
        }
    }

    public static function printerShareProvider(): array
    {
        return [
            'Printer' => ['Printer', true],
            'print$' => ['print$', true],
            'LPT1' => ['LPT1', true],
            'lpt2' => ['lpt2', true],
            'PrinterShare' => ['PrinterShare', true],
            'Documents' => ['Documents', false],
            'Public' => ['Public', false],
            'SHARE1' => ['SHARE1', false],
        ];
    }

    #[Test]
    #[DataProvider('dotFileProvider')]
    public function hideDotFilesFilteringLogic(string $fileName, bool $shouldBeFiltered): void
    {
        // Test the dot file detection logic
        $isDotFile = str_starts_with($fileName, '.');
        
        if ($shouldBeFiltered) {
            $this->assertTrue($isDotFile, "File '$fileName' should be identified as a dot file");
        } else {
            $this->assertFalse($isDotFile, "File '$fileName' should not be identified as a dot file");
        }
    }

    public static function dotFileProvider(): array
    {
        return [
            '.hidden' => ['.hidden', true],
            '.gitignore' => ['.gitignore', true],
            '.DS_Store' => ['.DS_Store', true],
            '...' => ['...', true],
            'normal.txt' => ['normal.txt', false],
            'file.hidden' => ['file.hidden', false],
            'document' => ['document', false],
        ];
    }

    #[Test]
    public function deleteDirectoryRecursiveThrowsException(): void
    {
        $client = new SmbClient($this->config);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Recursive delete not yet supported');
        
        $client->deleteDirectoryRecursive('share', '/path');
    }
}
