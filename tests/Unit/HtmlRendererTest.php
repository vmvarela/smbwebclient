<?php

declare(strict_types=1);

namespace SmbWebClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use SmbWebClient\HtmlRenderer;
use SmbWebClient\Config;
use SmbWebClient\Translator;
use SmbWebClient\Router;
use SmbWebClient\Session;
use SmbWebClient\InputValidator;

#[CoversClass(HtmlRenderer::class)]
class HtmlRendererTest extends TestCase
{
    private Config $config;
    private Translator $translator;
    private Router $router;
    private Session $session;
    private HtmlRenderer $renderer;
    
    protected function setUp(): void
    {
        // Suppress session warnings in tests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        $this->config = new Config(
            smbDefaultServer: 'testserver',
            defaultCharset: 'UTF-8',
        );
        $this->translator = new Translator('en');
        
        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PHP_SELF'] = '/index.php';
        
        $validator = new InputValidator();
        $this->router = new Router($this->config, $validator);
        $this->session = new Session($this->config);
        
        $this->renderer = new HtmlRenderer(
            $this->config,
            $this->translator,
            $this->router,
            $this->session,
            'windows',
        );
    }
    
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
    
    #[Test]
    public function getThemeNamesReturnsAllThemes(): void
    {
        $themes = $this->renderer->getThemeNames();
        
        $this->assertArrayHasKey('windows', $themes);
        $this->assertArrayHasKey('mac', $themes);
        $this->assertArrayHasKey('ubuntu', $themes);
    }
    
    #[Test]
    public function renderPageContainsTitle(): void
    {
        $html = $this->renderer->renderPage('Test Title', '<p>Content</p>');
        
        $this->assertStringContainsString('<title>Test Title</title>', $html);
    }
    
    #[Test]
    public function renderPageContainsContent(): void
    {
        $html = $this->renderer->renderPage('Test', '<p>Test Content</p>');
        
        $this->assertStringContainsString('<p>Test Content</p>', $html);
    }
    
    #[Test]
    public function renderPageIncludesThemeCss(): void
    {
        $html = $this->renderer->renderPage('Test', '');
        
        $this->assertStringContainsString('/assets/css/windows.css', $html);
    }
    
    #[Test]
    public function renderPageWithDifferentTheme(): void
    {
        $renderer = new HtmlRenderer(
            $this->config,
            $this->translator,
            $this->router,
            $this->session,
            'mac',
        );
        
        $html = $renderer->renderPage('Test', '');
        
        $this->assertStringContainsString('/assets/css/mac.css', $html);
    }
    
    #[Test]
    public function renderPageContainsCharset(): void
    {
        $html = $this->renderer->renderPage('Test', '');
        
        $this->assertStringContainsString('charset="UTF-8"', $html);
    }
    
    #[Test]
    public function renderLoginFormContainsForm(): void
    {
        $html = $this->renderer->renderLoginForm();
        
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('method="post"', $html);
    }
    
    #[Test]
    public function renderLoginFormContainsCsrfToken(): void
    {
        $html = $this->renderer->renderLoginForm();
        
        $this->assertStringContainsString('name="csrf_token"', $html);
    }
    
    #[Test]
    public function renderLoginFormContainsUsernameField(): void
    {
        $html = $this->renderer->renderLoginForm();
        
        $this->assertStringContainsString('name="swcUser"', $html);
    }
    
    #[Test]
    public function renderLoginFormContainsPasswordField(): void
    {
        $html = $this->renderer->renderLoginForm();
        
        $this->assertStringContainsString('name="swcPw"', $html);
        $this->assertStringContainsString('type="password"', $html);
    }
    
    #[Test]
    public function renderLoginFormContainsLanguageSelector(): void
    {
        $html = $this->renderer->renderLoginForm();
        
        $this->assertStringContainsString('name="swcLang"', $html);
    }
    
    #[Test]
    public function renderLoginFormContainsThemeSelector(): void
    {
        $html = $this->renderer->renderLoginForm();
        
        $this->assertStringContainsString('name="swcTheme"', $html);
    }
    
    #[Test]
    public function renderServerListContainsServers(): void
    {
        $servers = ['server1', 'server2'];
        
        $html = $this->renderer->renderServerList($servers);
        
        $this->assertStringContainsString('server1', $html);
        $this->assertStringContainsString('server2', $html);
    }
    
    #[Test]
    public function renderServerListContainsTable(): void
    {
        $servers = ['server1'];
        
        $html = $this->renderer->renderServerList($servers);
        
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('class="listing"', $html);
    }
    
    #[Test]
    public function renderServerListContainsBreadcrumb(): void
    {
        $servers = ['server1'];
        
        $html = $this->renderer->renderServerList($servers);
        
        $this->assertStringContainsString('breadcrumb', $html);
    }
    
    #[Test]
    public function renderErrorContainsMessage(): void
    {
        $html = $this->renderer->renderError('Test error message');
        
        $this->assertStringContainsString('Test error message', $html);
        $this->assertStringContainsString('Error:', $html);
    }
    
    #[Test]
    public function renderErrorEscapesHtml(): void
    {
        $html = $this->renderer->renderError('<script>alert("xss")</script>');
        
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
    
    #[Test]
    public function renderBreadcrumbReturnsRootForEmptyPath(): void
    {
        $html = $this->renderer->renderBreadcrumb();
        
        // Should contain the root label (Network in English)
        $this->assertStringContainsString('Network', $html);
    }
    
    #[Test]
    public function formatSizeFormatsBytes(): void
    {
        $this->assertSame('100 B', $this->renderer->formatSize(100));
    }
    
    #[Test]
    public function formatSizeFormatsKilobytes(): void
    {
        $this->assertSame('1 KB', $this->renderer->formatSize(1024));
    }
    
    #[Test]
    public function formatSizeFormatsMegabytes(): void
    {
        $this->assertSame('1 MB', $this->renderer->formatSize(1024 * 1024));
    }
    
    #[Test]
    public function formatSizeFormatsGigabytes(): void
    {
        $this->assertSame('1 GB', $this->renderer->formatSize(1024 * 1024 * 1024));
    }
    
    #[Test]
    public function formatSizeHandlesDecimal(): void
    {
        $result = $this->renderer->formatSize(1536); // 1.5 KB
        
        $this->assertSame('1.5 KB', $result);
    }
}
