<?php

declare(strict_types=1);

namespace SmbWebClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use SmbWebClient\Router;
use SmbWebClient\Config;
use SmbWebClient\InputValidator;

#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    private Config $config;
    private InputValidator $validator;
    
    protected function setUp(): void
    {
        $this->config = new Config(
            smbDefaultServer: 'testserver',
            smbRootPath: '',
            modRewrite: false,
            baseUrl: '/',
        );
        $this->validator = new InputValidator();
        
        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PHP_SELF'] = '/index.php';
    }
    
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    
    #[Test]
    public function getCurrentPathReturnsEmptyForNoPath(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('', $router->getCurrentPath());
    }
    
    #[Test]
    public function getCurrentPathReturnsPathFromGet(): void
    {
        $_GET['path'] = 'server/share/folder';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('server/share/folder', $router->getCurrentPath());
    }
    
    #[Test]
    public function getCurrentPathTrimsSlashes(): void
    {
        $_GET['path'] = '/server/share/';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('server/share', $router->getCurrentPath());
    }
    
    #[Test]
    public function getPathPartsReturnsArray(): void
    {
        $_GET['path'] = 'server/share/folder';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame(['server', 'share', 'folder'], $router->getPathParts());
    }
    
    #[Test]
    public function getPathDepthReturnsCorrectCount(): void
    {
        $_GET['path'] = 'server/share/folder';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame(3, $router->getPathDepth());
    }
    
    #[Test]
    public function getSortByReturnsDefaultName(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('name', $router->getSortBy());
    }
    
    #[Test]
    public function getSortByReturnsValueFromGet(): void
    {
        $_GET['sort'] = 'size';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('size', $router->getSortBy());
    }
    
    #[Test]
    public function getSortDirReturnsDefaultAsc(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('asc', $router->getSortDir());
    }
    
    #[Test]
    public function getSortDirReturnsValueFromGet(): void
    {
        $_GET['dir'] = 'desc';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('desc', $router->getSortDir());
    }
    
    #[Test]
    public function getActionReturnsNullForGet(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertNull($router->getAction());
    }
    
    #[Test]
    public function getActionReturnsActionFromPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action'] = 'upload';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('upload', $router->getAction());
    }
    
    #[Test]
    public function getActionReturnsNullForInvalidAction(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action'] = 'invalid';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertNull($router->getAction());
    }
    
    #[Test]
    public function isLogoutReturnsFalseByDefault(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertFalse($router->isLogout());
    }
    
    #[Test]
    public function isLogoutReturnsTrueWhenSet(): void
    {
        $_GET['logout'] = '1';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertTrue($router->isLogout());
    }
    
    #[Test]
    public function isDownloadReturnsFalseByDefault(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertFalse($router->isDownload());
    }
    
    #[Test]
    public function isDownloadReturnsTrueWhenSet(): void
    {
        $_GET['download'] = '1';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertTrue($router->isDownload());
    }
    
    #[Test]
    public function isPostReturnsFalseForGet(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertFalse($router->isPost());
    }
    
    #[Test]
    public function isPostReturnsTrueForPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertTrue($router->isPost());
    }
    
    #[Test]
    public function isLoginAttemptReturnsFalseByDefault(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertFalse($router->isLoginAttempt());
    }
    
    #[Test]
    public function isLoginAttemptReturnsTrueWhenSubmitted(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['swcSubmit'] = 'Login';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertTrue($router->isLoginAttempt());
    }
    
    #[Test]
    public function getServerShareAndPathReturnsEmptyForRoot(): void
    {
        $router = new Router($this->config, $this->validator);
        
        [$server, $share, $path] = $router->getServerShareAndPath();
        
        $this->assertSame('', $server);
        $this->assertSame('', $share);
        $this->assertSame('/', $path);
    }
    
    #[Test]
    public function getServerShareAndPathReturnsEmptyForServerOnly(): void
    {
        $_GET['path'] = 'server';
        
        $router = new Router($this->config, $this->validator);
        
        [$server, $share, $path] = $router->getServerShareAndPath();
        
        $this->assertSame('', $server);
        $this->assertSame('', $share);
        $this->assertSame('/', $path);
    }
    
    #[Test]
    public function getServerShareAndPathReturnsCorrectValues(): void
    {
        $_GET['path'] = 'server/share/folder/subfolder';
        
        $router = new Router($this->config, $this->validator);
        
        [$server, $share, $path] = $router->getServerShareAndPath();
        
        $this->assertSame('server', $server);
        $this->assertSame('share', $share);
        $this->assertSame('/folder/subfolder', $path);
    }
    
    #[Test]
    public function getServerShareAndPathReturnsRootForShareOnly(): void
    {
        $_GET['path'] = 'server/share';
        
        $router = new Router($this->config, $this->validator);
        
        [$server, $share, $path] = $router->getServerShareAndPath();
        
        $this->assertSame('server', $server);
        $this->assertSame('share', $share);
        $this->assertSame('/', $path);
    }
    
    #[Test]
    public function buildUrlWithoutModRewrite(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $url = $router->buildUrl('server/share');
        
        $this->assertSame('/index.php?path=server%2Fshare', $url);
    }
    
    #[Test]
    public function buildUrlWithModRewrite(): void
    {
        $config = new Config(
            smbDefaultServer: 'testserver',
            modRewrite: true,
            baseUrl: '/smb/',
        );
        
        $router = new Router($config, $this->validator);
        
        $url = $router->buildUrl('server/share');
        
        $this->assertSame('/smb/server/share?path=server%2Fshare', $url);
    }
    
    #[Test]
    public function buildUrlWithParams(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $url = $router->buildUrl('server/share', ['sort' => 'name', 'dir' => 'asc']);
        
        $this->assertStringContainsString('sort=name', $url);
        $this->assertStringContainsString('dir=asc', $url);
    }
    
    #[Test]
    public function getRouteTypeReturnsServersForRoot(): void
    {
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('servers', $router->getRouteType());
    }
    
    #[Test]
    public function getRouteTypeReturnsSharesForServer(): void
    {
        $_GET['path'] = 'server';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('shares', $router->getRouteType());
    }
    
    #[Test]
    public function getRouteTypeReturnsDirectoryForPath(): void
    {
        $_GET['path'] = 'server/share/folder';
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('directory', $router->getRouteType());
    }
    
    #[Test]
    public function invalidPathIsRejected(): void
    {
        $_GET['path'] = '../../../etc/passwd';
        
        $router = new Router($this->config, $this->validator);
        
        // Should fall back to root path
        $this->assertSame('', $router->getCurrentPath());
    }
    
    #[Test]
    public function getPostDataReturnsAllPostData(): void
    {
        $_POST = ['key1' => 'value1', 'key2' => 'value2'];
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $router->getPostData());
    }
    
    #[Test]
    public function getPostDataReturnsSpecificKey(): void
    {
        $_POST = ['key1' => 'value1', 'key2' => 'value2'];
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('value1', $router->getPostData('key1'));
    }
    
    #[Test]
    public function getPostDataReturnsNullForMissingKey(): void
    {
        $_POST = [];
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertNull($router->getPostData('nonexistent'));
    }
    
    #[Test]
    public function getQueryDataReturnsAllGetData(): void
    {
        $_GET = ['key1' => 'value1', 'key2' => 'value2'];
        
        $router = new Router($this->config, $this->validator);
        
        $data = $router->getQueryData();
        $this->assertArrayHasKey('key1', $data);
        $this->assertArrayHasKey('key2', $data);
    }
    
    #[Test]
    public function getQueryDataReturnsSpecificKey(): void
    {
        $_GET = ['key1' => 'value1', 'key2' => 'value2'];
        
        $router = new Router($this->config, $this->validator);
        
        $this->assertSame('value1', $router->getQueryData('key1'));
    }
    
    #[Test]
    public function backwardCompatibilityAttachesDefaultServer(): void
    {
        $config = new Config(
            smbDefaultServer: 'myserver',
            smbRootPath: 'myshare',
        );
        
        $router = new Router($config, $this->validator);
        
        $this->assertSame('myserver/myshare', $router->getCurrentPath());
        $this->assertSame(['myserver', 'myshare'], $router->getPathParts());
    }
}
