<?php

declare(strict_types=1);

namespace SmbWebClient;

class Config
{
    public function __construct(
        public readonly string $smbDefaultServer = 'localhost',
        /** @var string[] */
        public readonly array $smbServerList = [],
        public readonly string $smbRootPath = '',
        public readonly bool $hideDotFiles = true,
        public readonly bool $hideSystemShares = true,
        public readonly bool $hidePrinterShares = false,
        public readonly string $defaultLanguage = 'es',
        public readonly string $defaultCharset = 'UTF-8',
        public readonly string $cachePath = '',
        public readonly string $sessionName = 'SMBWebClientID',
        public readonly bool $allowAnonymous = false,
        public readonly bool $modRewrite = false,
        public readonly string $baseUrl = '',
        public readonly string $archiverType = 'zip',
        public readonly int $logLevel = 0,
        public readonly int $logFacility = LOG_DAEMON,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            smbDefaultServer: $_ENV['SMB_DEFAULT_SERVER'] ?? 'localhost',
            smbServerList: self::parseServerList($_ENV['SMB_SERVER_LIST'] ?? ''),
            smbRootPath: $_ENV['SMB_ROOT_PATH'] ?? '',
            hideDotFiles: filter_var($_ENV['SMB_HIDE_DOT_FILES'] ?? true, FILTER_VALIDATE_BOOLEAN),
            hideSystemShares: filter_var($_ENV['SMB_HIDE_SYSTEM_SHARES'] ?? true, FILTER_VALIDATE_BOOLEAN),
            hidePrinterShares: filter_var($_ENV['SMB_HIDE_PRINTER_SHARES'] ?? false, FILTER_VALIDATE_BOOLEAN),
            defaultLanguage: $_ENV['APP_DEFAULT_LANGUAGE'] ?? 'es',
            defaultCharset: $_ENV['APP_DEFAULT_CHARSET'] ?? 'UTF-8',
            cachePath: $_ENV['APP_CACHE_PATH'] ?? '',
            sessionName: $_ENV['APP_SESSION_NAME'] ?? 'SMBWebClientID',
            allowAnonymous: filter_var($_ENV['APP_ALLOW_ANONYMOUS'] ?? false, FILTER_VALIDATE_BOOLEAN),
            modRewrite: filter_var($_ENV['APP_MOD_REWRITE'] ?? false, FILTER_VALIDATE_BOOLEAN),
            baseUrl: $_ENV['APP_BASE_URL'] ?? '',
            archiverType: $_ENV['ARCHIVER_TYPE'] ?? 'zip',
            logLevel: (int)($_ENV['LOG_LEVEL'] ?? 0),
            logFacility: (int)($_ENV['LOG_FACILITY'] ?? LOG_DAEMON),
        );
    }

    private static function parseServerList(string $value): array
    {
        $servers = array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== '');
        // Remove duplicates while preserving order
        return array_values(array_unique($servers));
    }
}
