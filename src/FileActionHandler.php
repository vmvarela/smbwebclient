<?php

declare(strict_types=1);

namespace SmbWebClient;

/**
 * Handles all file operations (upload, download, delete, rename, mkdir).
 */
class FileActionHandler
{
    public function __construct(
        private readonly SmbClient $smbClient,
        private readonly Session $session,
        private readonly Router $router,
        private readonly InputValidator $validator,
        private readonly Config $config,
    ) {
    }

    /**
     * Handle file upload.
     */
    public function handleUpload(): void
    {
        if (!isset($_FILES['file'])) {
            return;
        }

        $rawFiles = $_FILES['file'];
        [$serverName, $shareName, $remotePath] = $this->router->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        
        // Normalize to array format
        $fileArray = [];
        if (is_array($rawFiles['name'])) {
            // Multiple files uploaded
            for ($i = 0; $i < count($rawFiles['name']); $i++) {
                $fileArray[] = [
                    'name' => $rawFiles['name'][$i],
                    'tmp_name' => $rawFiles['tmp_name'][$i],
                    'error' => $rawFiles['error'][$i],
                ];
            }
        } else {
            // Single file uploaded
            $fileArray[] = $rawFiles;
        }
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($fileArray as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorCount++;
                $errors[] = 'Error: ' . basename($file['name']);
                continue;
            }

            // Validate and sanitize filename
            $rawFilename = basename($file['name']);
            try {
                $sanitizedFilename = $this->validator->sanitizeFilename($rawFilename);
            } catch (\InvalidArgumentException $e) {
                $errorCount++;
                $errors[] = 'Invalid filename: ' . $this->validator->escapeHtml($rawFilename);
                continue;
            }

            // Build the full remote path
            $fullRemotePath = rtrim($remotePath, '/') . '/' . $sanitizedFilename;
            
            // Extract directory path and create it if needed
            $fileDir = dirname($fullRemotePath);
            if ($fileDir !== '/' && $fileDir !== $remotePath) {
                try {
                    $this->smbClient->createDirectory($serverName, $shareName, $fileDir);
                } catch (\Exception $e) {
                    // Directory might already exist, continue
                }
            }

            try {
                $this->smbClient->uploadFile($serverName, $shareName, $file['tmp_name'], $fullRemotePath);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = $this->validator->escapeHtml($sanitizedFilename) . ': ' . $e->getMessage();
            }
        }

        if ($successCount > 0) {
            $this->session->setSuccessMessage("Uploaded $successCount file(s)");
        }
        if ($errorCount > 0) {
            $this->session->setErrorMessage(implode(', ', $errors));
        }
    }

    /**
     * Handle directory creation.
     */
    public function handleCreateDirectory(): void
    {
        $rawDirName = $_POST['dirname'] ?? '';
        if (empty($rawDirName)) {
            return;
        }

        // Validate directory name
        try {
            $dirName = $this->validator->sanitizeFilename($rawDirName);
        } catch (\InvalidArgumentException $e) {
            $this->session->setErrorMessage('Invalid directory name: ' . $e->getMessage());
            return;
        }

        [$serverName, $shareName, $remotePath] = $this->router->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        $remotePath = rtrim($remotePath, '/') . '/' . $dirName;

        try {
            $this->smbClient->createDirectory($serverName, $shareName, $remotePath);
        } catch (\Exception $e) {
            $this->session->setErrorMessage('Error creating directory: ' . $e->getMessage());
        }
    }

    /**
     * Handle file/directory deletion.
     */
    public function handleDelete(): void
    {
        $rawItems = isset($_POST['items']) ? (array)$_POST['items'] : [];
        $rawItems = array_filter(array_map('trim', $rawItems));
        
        if (empty($rawItems)) {
            $this->session->setErrorMessage('No items selected for deletion');
            return;
        }

        // Validate all filenames
        try {
            $items = $this->validator->sanitizeFilenameList($rawItems);
        } catch (\InvalidArgumentException $e) {
            $this->session->setErrorMessage('Invalid filename: ' . $e->getMessage());
            return;
        }

        [$serverName, $shareName, $remotePath] = $this->router->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        $successCount = 0;
        $errorCount = 0;

        foreach ($items as $item) {
            // Build proper path: if remotePath is '/', use just the filename
            if ($remotePath === '/') {
                $itemPath = '/' . $item;
            } else {
                $itemPath = rtrim($remotePath, '/') . '/' . $item;
            }
            
            if ($this->config->logLevel > 0) {
                error_log("Attempting to delete: server=$serverName, share=$shareName, path=$itemPath");
            }
            
            $deleted = false;
            
            // Try as file first
            try {
                $this->smbClient->deleteFile($serverName, $shareName, $itemPath);
                $deleted = true;
            } catch (\Exception $fileError) {
                // Try as empty directory
                try {
                    $this->smbClient->deleteDirectory($serverName, $shareName, $itemPath);
                    $deleted = true;
                } catch (\Exception $dirError) {
                    $errorCount++;
                    $this->session->setErrorMessage('Error deleting ' . $this->validator->escapeHtml($item) . ': ' . $dirError->getMessage());
                }
            }
            
            if ($deleted) {
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            $this->session->setSuccessMessage($successCount . ' item(s) deleted successfully');
        }
    }

    /**
     * Handle check for non-empty directories before deletion.
     */
    public function handleCheckDelete(): void
    {
        header('Content-Type: application/json');
        
        try {
            $rawItems = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
            if (!is_array($rawItems)) {
                echo json_encode(['nonEmptyDirs' => [], 'error' => 'Invalid items format']);
                exit;
            }
            $rawItems = array_filter(array_map('trim', $rawItems));
            
            if (empty($rawItems)) {
                echo json_encode(['nonEmptyDirs' => [], 'error' => null]);
                exit;
            }

            // Validate all filenames
            try {
                $items = $this->validator->sanitizeFilenameList($rawItems);
            } catch (\InvalidArgumentException $e) {
                echo json_encode(['nonEmptyDirs' => [], 'error' => 'Invalid filename']);
                exit;
            }

            try {
                [$serverName, $shareName, $remotePath] = $this->router->getServerShareAndPath();
                if ($serverName === '' || $shareName === '') {
                    echo json_encode(['nonEmptyDirs' => [], 'error' => 'Ruta inválida']);
                    exit;
                }
            } catch (\Exception $e) {
                echo json_encode(['nonEmptyDirs' => [], 'error' => 'Connection error']);
                exit;
            }

            $nonEmptyDirs = [];

            foreach ($items as $item) {
                // Build proper path: if remotePath is '/', use just the filename
                if ($remotePath === '/') {
                    $itemPath = '/' . $item;
                } else {
                    $itemPath = rtrim($remotePath, '/') . '/' . $item;
                }
                
                try {
                    $this->smbClient->deleteDirectory($serverName, $shareName, $itemPath);
                } catch (\Exception $e) {
                    $message = $e->getMessage();
                    if (stripos($message, 'not empty') !== false || 
                        stripos($message, 'notempty') !== false ||
                        stripos($message, 'NT_STATUS_DIRECTORY_NOT_EMPTY') !== false) {
                        $nonEmptyDirs[] = $this->validator->escapeHtml($item);
                    }
                }
            }

            echo json_encode(['nonEmptyDirs' => $nonEmptyDirs, 'error' => null]);
            exit;
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['nonEmptyDirs' => [], 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Handle file/directory rename.
     */
    public function handleRename(): void
    {
        $rawOldName = $_POST['oldname'] ?? '';
        $rawNewName = $_POST['newname'] ?? '';
        
        if (empty($rawOldName) || empty($rawNewName)) {
            return;
        }

        // Validate filenames
        try {
            $oldName = $this->validator->sanitizeFilename($rawOldName);
            $newName = $this->validator->sanitizeFilename($rawNewName);
        } catch (\InvalidArgumentException $e) {
            $this->session->setErrorMessage('Invalid filename: ' . $e->getMessage());
            return;
        }

        [$serverName, $shareName, $remotePath] = $this->router->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        $oldPath = rtrim($remotePath, '/') . '/' . $oldName;
        $newPath = rtrim($remotePath, '/') . '/' . $newName;

        try {
            $this->smbClient->rename($serverName, $shareName, $oldPath, $newPath);
        } catch (\Exception $e) {
            $this->session->setErrorMessage('Error renaming: ' . $e->getMessage());
        }
    }

    /**
     * Handle file download.
     */
    public function handleDownload(): void
    {
        [$serverName, $shareName, $remotePath] = $this->router->getServerShareAndPath();

        if ($serverName === '' || $shareName === '' || $remotePath === '/' || str_ends_with($remotePath, '/')) {
            $this->session->setErrorMessage('Ruta de descarga inválida');
            $this->router->redirectToPath($this->router->getCurrentPath());
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'swc');

        try {
            $this->smbClient->downloadFile($serverName, $shareName, $remotePath, $tempFile);
            $fileName = basename($remotePath);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($tempFile));
            readfile($tempFile);
        } catch (\Exception $e) {
            $this->session->setErrorMessage('Error al descargar: ' . $e->getMessage());
            $this->router->redirectToPath($this->router->getCurrentPath());
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        exit;
    }

    /**
     * Dispatch an action based on the action name.
     * 
     * @return bool True if action was handled, false otherwise
     */
    public function dispatch(string $action): bool
    {
        return match ($action) {
            'upload' => $this->handleUploadAndReturn(),
            'mkdir' => $this->handleCreateDirectoryAndReturn(),
            'delete' => $this->handleDeleteAndReturn(),
            'checkdelete' => $this->handleCheckDeleteAndReturn(),
            'rename' => $this->handleRenameAndReturn(),
            default => false,
        };
    }

    private function handleUploadAndReturn(): bool
    {
        $this->handleUpload();
        return true;
    }

    private function handleCreateDirectoryAndReturn(): bool
    {
        $this->handleCreateDirectory();
        return true;
    }

    private function handleDeleteAndReturn(): bool
    {
        $this->handleDelete();
        return true;
    }

    private function handleCheckDeleteAndReturn(): bool
    {
        $this->handleCheckDelete();
        return true;
    }

    private function handleRenameAndReturn(): bool
    {
        $this->handleRename();
        return true;
    }
}
