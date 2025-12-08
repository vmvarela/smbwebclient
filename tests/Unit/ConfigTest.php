<?php

declare(strict_types=1);

namespace SmbWebClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use SmbWebClient\Config;

class ConfigTest extends TestCase
{
    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        $config = new Config();

        $this->assertSame('localhost', $config->smbDefaultServer);
        $this->assertSame([], $config->smbServerList);
        $this->assertSame('', $config->smbRootPath);
        $this->assertTrue($config->hideDotFiles);
        $this->assertTrue($config->hideSystemShares);
        $this->assertFalse($config->hidePrinterShares);
        $this->assertSame('es', $config->defaultLanguage);
        $this->assertSame('UTF-8', $config->defaultCharset);
        $this->assertSame('', $config->cachePath);
        $this->assertSame('SMBWebClientID', $config->sessionName);
        $this->assertFalse($config->allowAnonymous);
        $this->assertFalse($config->modRewrite);
        $this->assertSame('', $config->baseUrl);
        $this->assertSame('zip', $config->archiverType);
        $this->assertSame(0, $config->logLevel);
        $this->assertSame(LOG_DAEMON, $config->logFacility);
    }

    #[Test]
    public function constructorAcceptsCustomValues(): void
    {
        $config = new Config(
            smbDefaultServer: 'myserver',
            smbServerList: ['server1', 'server2'],
            smbRootPath: '/share',
            hideDotFiles: false,
            hideSystemShares: false,
            hidePrinterShares: true,
            defaultLanguage: 'en',
            defaultCharset: 'ISO-8859-1',
            cachePath: '/tmp/cache',
            sessionName: 'CustomSession',
            allowAnonymous: true,
            modRewrite: true,
            baseUrl: '/smb',
            archiverType: 'tar',
            logLevel: 3,
            logFacility: LOG_LOCAL0,
        );

        $this->assertSame('myserver', $config->smbDefaultServer);
        $this->assertSame(['server1', 'server2'], $config->smbServerList);
        $this->assertSame('/share', $config->smbRootPath);
        $this->assertFalse($config->hideDotFiles);
        $this->assertFalse($config->hideSystemShares);
        $this->assertTrue($config->hidePrinterShares);
        $this->assertSame('en', $config->defaultLanguage);
        $this->assertSame('ISO-8859-1', $config->defaultCharset);
        $this->assertSame('/tmp/cache', $config->cachePath);
        $this->assertSame('CustomSession', $config->sessionName);
        $this->assertTrue($config->allowAnonymous);
        $this->assertTrue($config->modRewrite);
        $this->assertSame('/smb', $config->baseUrl);
        $this->assertSame('tar', $config->archiverType);
        $this->assertSame(3, $config->logLevel);
        $this->assertSame(LOG_LOCAL0, $config->logFacility);
    }

    #[Test]
    public function fromEnvUsesDefaultsWhenEnvNotSet(): void
    {
        // Clear relevant environment variables
        $envVars = [
            'SMB_DEFAULT_SERVER', 'SMB_SERVER_LIST', 'SMB_ROOT_PATH',
            'SMB_HIDE_DOT_FILES', 'SMB_HIDE_SYSTEM_SHARES', 'SMB_HIDE_PRINTER_SHARES',
            'APP_DEFAULT_LANGUAGE', 'APP_DEFAULT_CHARSET', 'APP_CACHE_PATH',
            'APP_SESSION_NAME', 'APP_ALLOW_ANONYMOUS', 'APP_MOD_REWRITE',
            'APP_BASE_URL', 'ARCHIVER_TYPE', 'LOG_LEVEL', 'LOG_FACILITY',
        ];
        
        $originalEnv = [];
        foreach ($envVars as $var) {
            $originalEnv[$var] = $_ENV[$var] ?? null;
            unset($_ENV[$var]);
        }

        try {
            $config = Config::fromEnv();

            $this->assertSame('localhost', $config->smbDefaultServer);
            $this->assertSame([], $config->smbServerList);
            $this->assertTrue($config->hideDotFiles);
            $this->assertFalse($config->allowAnonymous);
        } finally {
            // Restore environment
            foreach ($originalEnv as $var => $value) {
                if ($value !== null) {
                    $_ENV[$var] = $value;
                }
            }
        }
    }

    #[Test]
    public function fromEnvParsesEnvironmentVariables(): void
    {
        // Set up environment variables
        $_ENV['SMB_DEFAULT_SERVER'] = 'testserver';
        $_ENV['SMB_SERVER_LIST'] = 'server1, server2, server3';
        $_ENV['SMB_HIDE_DOT_FILES'] = 'false';
        $_ENV['APP_ALLOW_ANONYMOUS'] = 'true';
        $_ENV['APP_DEFAULT_LANGUAGE'] = 'fr';
        $_ENV['LOG_LEVEL'] = '5';

        try {
            $config = Config::fromEnv();

            $this->assertSame('testserver', $config->smbDefaultServer);
            $this->assertSame(['server1', 'server2', 'server3'], $config->smbServerList);
            $this->assertFalse($config->hideDotFiles);
            $this->assertTrue($config->allowAnonymous);
            $this->assertSame('fr', $config->defaultLanguage);
            $this->assertSame(5, $config->logLevel);
        } finally {
            // Clean up
            unset(
                $_ENV['SMB_DEFAULT_SERVER'],
                $_ENV['SMB_SERVER_LIST'],
                $_ENV['SMB_HIDE_DOT_FILES'],
                $_ENV['APP_ALLOW_ANONYMOUS'],
                $_ENV['APP_DEFAULT_LANGUAGE'],
                $_ENV['LOG_LEVEL']
            );
        }
    }

    #[Test]
    #[DataProvider('serverListProvider')]
    public function fromEnvParsesServerListCorrectly(string $input, array $expected): void
    {
        $_ENV['SMB_SERVER_LIST'] = $input;

        try {
            $config = Config::fromEnv();
            $this->assertSame($expected, $config->smbServerList);
        } finally {
            unset($_ENV['SMB_SERVER_LIST']);
        }
    }

    public static function serverListProvider(): array
    {
        return [
            'empty string' => ['', []],
            'single server' => ['server1', ['server1']],
            'multiple servers' => ['server1,server2,server3', ['server1', 'server2', 'server3']],
            'with spaces' => ['server1, server2 , server3', ['server1', 'server2', 'server3']],
            'with duplicates' => ['server1,server2,server1', ['server1', 'server2']],
            'with empty entries' => ['server1,,server2,', ['server1', 'server2']],
        ];
    }

    #[Test]
    public function configPropertiesAreReadonly(): void
    {
        $config = new Config();
        
        $reflection = new \ReflectionClass($config);
        
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property {$property->getName()} should be readonly"
            );
        }
    }
}
