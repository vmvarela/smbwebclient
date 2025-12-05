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
    private ?IServer $server = null;
    private ?IShare $share = null;

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
        $this->server = null; // Reset connection
        $this->share = null;
    }

    public function getServer(string $host = null): IServer
    {
        if ($this->server !== null && $host === null) {
            return $this->server;
        }

        $host = $host ?? $this->config->smbDefaultServer;
        $factory = new ServerFactory();

        if ($this->username && $this->password) {
            $auth = new BasicAuth($this->username, '', $this->password);
            $this->server = $factory->createServer($host, $auth);
        } else {
            $auth = new BasicAuth('guest', '', '');
            $this->server = $factory->createServer($host, $auth);
        }

        return $this->server;
    }

    public function getShare(string $shareName): IShare
    {
        $server = $this->getServer();
        $this->share = $server->getShare($shareName);
        return $this->share;
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
                
                // Filter printer shares
                if ($this->config->hidePrinterShares && $share->getType() === IShare::TYPE_PRINTER) {
                    return false;
                }
                
                return true;
            });
        } catch (AuthenticationException $e) {
            throw new \RuntimeException('Authentication failed: ' . $e->getMessage(), 0, $e);
        } catch (InvalidHostException $e) {
            throw new \RuntimeException('Invalid host: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listDirectory(string $shareName, string $path = '/'): array
    {
        $share = $this->getShare($shareName);
        $contents = $share->dir($path);
        
        return array_filter(iterator_to_array($contents), function($item) {
            if ($this->config->hideDotFiles && str_starts_with($item->getName(), '.')) {
                return false;
            }
            return $item->getName() !== '.' && $item->getName() !== '..';
        });
    }

    public function downloadFile(string $shareName, string $remotePath, string $localPath): void
    {
        $share = $this->getShare($shareName);
        $readStream = $share->read($remotePath);
        $writeStream = fopen($localPath, 'wb');

        if ($writeStream === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal de descarga');
        }

        stream_copy_to_stream($readStream, $writeStream);

        fclose($writeStream);
        fclose($readStream);
    }

    public function uploadFile(string $shareName, string $localPath, string $remotePath): void
    {
        $share = $this->getShare($shareName);
        $share->put($localPath, $remotePath);
    }

    public function deleteFile(string $shareName, string $path): void
    {
        $share = $this->getShare($shareName);
        $share->del($path);
    }

    public function deleteDirectory(string $shareName, string $path): void
    {
        $share = $this->getShare($shareName);
        $share->rmdir($path);
    }

    public function isDirectoryEmpty(string $shareName, string $path): bool
    {
        try {
            $share = $this->getShare($shareName);
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

    private function deleteDirectoryContents($share, string $path): void
    {
        try {
            $items = array_filter(iterator_to_array($share->dir($path)), function($item) {
                return $item->getName() !== '.' && $item->getName() !== '..';
            });
            foreach ($items as $item) {
                $itemPath = rtrim($path, '/') . '/' . $item->getName();
                if ($item->isDirectory()) {
                    $this->deleteDirectoryContents($share, $itemPath);
                    $share->rmdir($itemPath);
                } else {
                    $share->del($itemPath);
                }
            }
        } catch (\Exception $e) {
            // Continue deleting even if some items fail
        }
    }

    public function createDirectory(string $shareName, string $path): void
    {
        $share = $this->getShare($shareName);
        $share->mkdir($path);
    }

    public function rename(string $shareName, string $oldPath, string $newPath): void
    {
        $share = $this->getShare($shareName);
        $share->rename($oldPath, $newPath);
    }

    public function getFileInfo(string $shareName, string $path): \Icewind\SMB\IFileInfo
    {
        $share = $this->getShare($shareName);
        return $share->stat($path);
    }
}
