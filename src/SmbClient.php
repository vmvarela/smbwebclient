<?php

declare(strict_types=1);

namespace SmbWebClient;

use Icewind\SMB\IServer;
use Icewind\SMB\ServerFactory;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\IShare;
use Icewind\SMB\Exception\AuthenticationException;
use Icewind\SMB\Exception\InvalidHostException;

class SmbClient
{
    /** @var array<string,IServer> */
    private array $servers = [];

    public function __construct(
        private readonly Config $config,
        private ?string $username = null,
        private ?string $password = null,
    ) {
    }

    public function setCredentials(string $username, string $password): void
    {
        $this->username = $username;
        $this->password = $password;
        $this->servers = [];
    }

    /**
     * Return configured servers; if none provided, try NetBIOS broadcast; always falls back to default server.
     *
     * @return string[]
     */
    public function discoverServers(): array
    {
        if (!empty($this->config->smbServerList)) {
            return $this->config->smbServerList;
        }

        $discovered = $this->discoverViaNmblookup();
        if (!empty($discovered)) {
            return $discovered;
        }

        return [$this->config->smbDefaultServer];
    }

    public function getServer(?string $host = null): IServer
    {
        $host = $host ?? $this->config->smbDefaultServer;
        if (isset($this->servers[$host])) {
            return $this->servers[$host];
        }
        $factory = new ServerFactory();

        if ($this->username && $this->password) {
            $auth = new BasicAuth($this->username, '', $this->password);
        } else {
            // For anonymous/guest access, use empty credentials
            $auth = new BasicAuth('', '', '');
        }
        
        $this->servers[$host] = $factory->createServer($host, $auth);
        return $this->servers[$host];
    }

    public function getShare(string $serverHost, string $shareName): IShare
    {
        $server = $this->getServer($serverHost);
        return $server->getShare($shareName);
    }

    public function listShares(?string $host = null): array
    {
        try {
            $server = $this->getServer($host);
            $shares = $server->listShares();
            
            return array_filter($shares, function($share) {
                $name = $share->getName();
                
                // Filter system shares
                if ($this->config->hideSystemShares && 
                    (str_ends_with($name, '$') || in_array($name, ['IPC$', 'ADMIN$']))) {
                    return false;
                }
                
                // Filter printer shares (check share name pattern)
                if ($this->config->hidePrinterShares) {
                    $shareName = $share->getName();
                    // Printer shares typically end with $, but also check for common patterns
                    if (str_ends_with($shareName, '$') || 
                        stripos($shareName, 'print') !== false ||
                        stripos($shareName, 'lpt') !== false) {
                        return false;
                    }
                }
                
                // Validate that share is actually accessible to filter out
                // spurious entries sometimes reported by libsmbclient
                try {
                    $share->stat('.');
                    return true;
                } catch (\Exception) {
                    // Share is not accessible, filter it out
                    return false;
                }
            });
        } catch (AuthenticationException $e) {
            throw new \RuntimeException('Authentication failed: ' . $e->getMessage(), 0, $e);
        } catch (InvalidHostException $e) {
            throw new \RuntimeException('Invalid host: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Best-effort NetBIOS discovery using nmblookup. Returns unique hostnames if available.
     *
     * @return string[]
     */
    private function discoverViaNmblookup(): array
    {
        $output = @shell_exec('nmblookup -S 255.255.255.255 2>/dev/null');
        if (!$output) {
            return [];
        }

        preg_match_all('/^([A-Za-z0-9._-]+)<[0-9A-F]{2}>/m', $output, $matches);
        if (empty($matches[1])) {
            return [];
        }

        // Filter out the wildcard name *
        $names = array_filter($matches[1], fn($name) => $name !== '*');
        return array_values(array_unique($names));
    }

    public function listDirectory(string $serverHost, string $shareName, string $path = '/'): array
    {
        $share = $this->getShare($serverHost, $shareName);
        $contents = $share->dir($path);
        
        return array_filter(iterator_to_array($contents), function($item) {
            if ($this->config->hideDotFiles && str_starts_with($item->getName(), '.')) {
                return false;
            }
            return $item->getName() !== '.' && $item->getName() !== '..';
        });
    }

    public function downloadFile(string $serverHost, string $shareName, string $remotePath, string $localPath): void
    {
        $share = $this->getShare($serverHost, $shareName);
        $readStream = $share->read($remotePath);
        $writeStream = fopen($localPath, 'wb');

        if ($writeStream === false) {
            throw new \RuntimeException('Failed to create temporary download file');
        }

        stream_copy_to_stream($readStream, $writeStream);

        fclose($writeStream);
        fclose($readStream);
    }

    public function uploadFile(string $serverHost, string $shareName, string $localPath, string $remotePath): void
    {
        $share = $this->getShare($serverHost, $shareName);
        $share->put($localPath, $remotePath);
    }

    public function deleteFile(string $serverHost, string $shareName, string $path): void
    {
        $share = $this->getShare($serverHost, $shareName);
        $share->del($path);
    }

    public function deleteDirectory(string $serverHost, string $shareName, string $path): void
    {
        $share = $this->getShare($serverHost, $shareName);
        $share->rmdir($path);
    }

    public function isDirectoryEmpty(string $serverHost, string $shareName, string $path): bool
    {
        try {
            $share = $this->getShare($serverHost, $shareName);
            // Try to list directory contents
            foreach ($share->dir($path) as $item) {
                // If we find any item that's not . or .., it's not empty
                if ($item->getName() !== '.' && $item->getName() !== '..') {
                    return false;
                }
            }
            // No items found (except . and ..), so it's empty
            return true;
        } catch (\Exception $e) {
            // If listing fails, we assume the directory is not empty to be safe
            // This triggers the confirmation dialog
            return false;
        }
    }

    public function deleteDirectoryRecursive(string $shareName, string $path): void
    {
        // Temporarily disabled - icewind/smb doesn't support listing directories reliably
        throw new \Exception("Recursive delete not yet supported");
    }

    public function createDirectory(string $serverHost, string $shareName, string $path): void
    {
        $share = $this->getShare($serverHost, $shareName);
        $share->mkdir($path);
    }

    public function rename(string $serverHost, string $shareName, string $oldPath, string $newPath): void
    {
        $share = $this->getShare($serverHost, $shareName);
        $share->rename($oldPath, $newPath);
    }

    public function getFileInfo(string $serverHost, string $shareName, string $path): \Icewind\SMB\IFileInfo
    {
        $share = $this->getShare($serverHost, $shareName);
        return $share->stat($path);
    }
}
