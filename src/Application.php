<?php

declare(strict_types=1);

namespace SmbWebClient;

/**
 * Main application controller.
 * 
 * Coordinates routing, authentication, file operations, and rendering.
 * Acts as a thin controller delegating to specialized classes.
 */
class Application
{
    private SmbClient $smbClient;
    private Session $session;
    private Translator $translator;
    private InputValidator $validator;
    private RateLimiter $rateLimiter;
    private Router $router;
    private HtmlRenderer $renderer;
    private FileActionHandler $fileHandler;
    private string $theme = 'windows';

    public function __construct(
        private readonly Config $config,
    ) {
        $this->session = new Session($config);
        $this->validator = new InputValidator();
        
        // Initialize rate limiter for login protection
        $cacheDir = $config->cachePath ?: sys_get_temp_dir() . '/smbwebclient';
        $this->rateLimiter = new RateLimiter($cacheDir);
        
        // Resolve language: first session, then GET, then browser detection
        $defaultLanguage = $config->defaultLanguage;
        $sessionLanguage = $this->session->getLanguage();
        
        if ($sessionLanguage) {
            $language = $sessionLanguage;
        } else {
            $languageDetector = new Translator($defaultLanguage);
            $language = $_GET['lang'] ?? $languageDetector->detectLanguage($defaultLanguage);
        }
        
        $this->translator = new Translator($language);
        
        // Resolve theme: first session, then GET, then default
        $validThemes = ['windows', 'mac', 'ubuntu'];
        $sessionTheme = $this->session->getTheme();
        if ($sessionTheme) {
            $this->theme = $sessionTheme;
        } else {
            $rawTheme = $_GET['theme'] ?? 'windows';
            $this->theme = $this->validator->validateTheme($rawTheme, $validThemes, 'windows');
        }
        
        $credentials = $this->session->getCredentials();
        $this->smbClient = new SmbClient(
            $config,
            $credentials['username'] ?: null,
            $credentials['password'] ?: null,
        );

        // Initialize router
        $this->router = new Router($config, $this->validator);
        
        // Initialize renderer
        $this->renderer = new HtmlRenderer(
            $config,
            $this->translator,
            $this->router,
            $this->session,
            $this->theme,
        );
        
        // Initialize file action handler
        $this->fileHandler = new FileActionHandler(
            $this->smbClient,
            $this->session,
            $this->router,
            $this->validator,
            $config,
        );
    }

    /**
     * Run the application.
     */
    public function run(): void
    {
        // Handle authentication
        if (!$this->session->isAuthenticated()) {
            $this->handleAuthentication();
            return;
        }

        // Handle logout
        if ($this->router->isLogout()) {
            $this->handleLogout();
            return;
        }

        // Handle file actions (POST requests)
        if ($this->router->isPost()) {
            $this->handleAction();
        }

        // Handle download
        if ($this->router->isDownload()) {
            $this->fileHandler->handleDownload();
            return;
        }

        // Display content
        $this->displayContent();
    }

    /**
     * Handle authentication (login).
     */
    private function handleAuthentication(): void
    {
        // Check if this is a POST request to the login page
        if ($this->router->isPost() && isset($_POST['csrf_token'])) {
            // Always validate and save language and theme preferences first
            $language = $this->validator->validateLanguage(
                $_POST['swcLang'] ?? 'es',
                array_keys($this->getLanguageNames()),
                'es'
            );
            $theme = $this->validator->validateTheme(
                $_POST['swcTheme'] ?? 'windows',
                array_keys($this->getThemeNames()),
                'windows'
            );
            
            // Check if this is just a preference change (not a login attempt)
            $isPreferenceChange = isset($_POST['preference_change']) && $_POST['preference_change'] === '1';
            
            $this->session->setLanguage($language);
            $this->session->setTheme($theme);
            
            if ($isPreferenceChange) {
                // Force session write before redirect
                session_write_close();
                // Just preference change, redirect to reload with new settings
                $this->router->redirectToPath($this->router->getCurrentPath());
            }
            
            // This is a login attempt
            $username = $this->validator->sanitizeUsername($_POST['swcUser'] ?? '');
            $password = $_POST['swcPw'] ?? '';
            
            // Rate limiting check for actual login attempts
            $clientIp = RateLimiter::getClientIp();
            if ($this->rateLimiter->isLimited($clientIp)) {
                $remainingTime = $this->rateLimiter->getSecondsUntilUnlock($clientIp);
                $minutes = ceil($remainingTime / 60);
                $this->session->setErrorMessage(
                    "Demasiados intentos fallidos. Por favor, espera {$minutes} minuto(s) antes de intentarlo de nuevo."
                );
                $this->displayLoginForm();
                return;
            }

            // Validate CSRF token for login attempts
            if (!$this->session->validateCsrfToken($_POST['csrf_token'])) {
                $this->session->setErrorMessage('Invalid security token. Please try again.');
                $this->displayLoginForm();
                return;
            }
            
            // If only username or password is provided (not both), show error
            if (empty($username) || empty($password)) {
                $this->session->setErrorMessage('Por favor, ingresa usuario y contraseña.');
                $this->displayLoginForm();
                return;
            }
            
            $this->session->setCredentials($username, $password);
            $this->smbClient->setCredentials($username, $password);
            
            // Test credentials by trying to list shares on default server
            try {
                $this->smbClient->listShares($this->config->smbDefaultServer);
                
                // Authentication successful - reset rate limiter
                $this->rateLimiter->recordSuccess($clientIp);
                
                // Regenerate session ID to prevent session fixation attacks
                $this->session->regenerate();
                $this->session->regenerateCsrfToken();
                
                $this->router->redirectToPath($this->router->getCurrentPath());
            } catch (\Exception $e) {
                // Authentication failed - record attempt and show error
                $this->rateLimiter->recordFailedAttempt($clientIp);
                $remaining = $this->rateLimiter->getRemainingAttempts($clientIp);
                
                $this->session->clearCredentials();
                
                if ($remaining > 0) {
                    $this->session->setErrorMessage(
                        "Credenciales incorrectas. Te quedan {$remaining} intento(s)."
                    );
                } else {
                    $this->session->setErrorMessage(
                        'Credenciales incorrectas. Has alcanzado el límite de intentos.'
                    );
                }
            }
        }

        $this->displayLoginForm();
    }

    /**
     * Handle logout.
     */
    private function handleLogout(): void
    {
        // Preserve language and theme before destroying session
        $language = $this->session->getLanguage();
        $theme = $this->session->getTheme();
        
        $this->session->clearCredentials();
        $this->session->destroy();
        
        // Redirect with preserved language and theme
        $params = [];
        if ($language) {
            $params['lang'] = $language;
        }
        if ($theme) {
            $params['theme'] = $theme;
        }
        
        $this->router->redirectToPath('', $params);
    }

    /**
     * Handle file actions (POST requests).
     */
    private function handleAction(): void
    {
        // Validate CSRF token
        if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->session->setErrorMessage('Invalid security token. Please try again.');
            $this->router->redirectToPath($this->router->getCurrentPath());
        }

        $action = $this->router->getAction();
        if ($action !== null) {
            $handled = $this->fileHandler->dispatch($action);
            if ($handled && $action !== 'checkdelete') {
                $this->router->redirectToPath($this->router->getCurrentPath());
            }
        }
    }

    /**
     * Display the main content based on current route.
     */
    private function displayContent(): void
    {
        $content = match ($this->router->getRouteType()) {
            'servers' => $this->displayServers(),
            'shares' => $this->displayShares(),
            'directory' => $this->displayDirectory(),
            default => $this->displayServers(),
        };

        echo $this->renderer->renderPage('SMB Web Client', $content);
    }

    /**
     * Display the login form.
     */
    private function displayLoginForm(): void
    {
        $content = $this->renderer->renderLoginForm();
        echo $this->renderer->renderPage('Login - SMB Web Client', $content);
    }

    /**
     * Display the server list.
     */
    private function displayServers(): string
    {
        $servers = $this->smbClient->discoverServers();
        return $this->renderer->renderServerList($servers);
    }

    /**
     * Display the share list for a server.
     */
    private function displayShares(): string
    {
        $serverName = $this->router->getPathParts()[0] ?? '';
        try {
            $shares = $this->smbClient->listShares($serverName);
            return $this->renderer->renderShareList($serverName, $shares);
        } catch (\Exception $e) {
            return $this->renderer->renderError('Error listing shares: ' . $e->getMessage());
        }
    }

    /**
     * Display the directory listing.
     */
    private function displayDirectory(): string
    {
        [$serverName, $shareName, $remotePath] = $this->router->getServerShareAndPath();
        try {
            $items = $this->smbClient->listDirectory($serverName, $shareName, $remotePath);
            $items = $this->renderer->sortItems($items);
            return $this->renderer->renderDirectoryList($items);
        } catch (\Exception $e) {
            return $this->renderer->renderError('Error listing directory: ' . $e->getMessage());
        }
    }

    /**
     * Get available language names.
     */
    private function getLanguageNames(): array
    {
        return $this->translator->getAvailableLanguages();
    }

    /**
     * Get available theme names.
     */
    private function getThemeNames(): array
    {
        return $this->renderer->getThemeNames();
    }
}
