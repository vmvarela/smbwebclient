<?php

declare(strict_types=1);

namespace SmbWebClient;

use Icewind\SMB\IFileInfo;

/**
 * Handles all HTML rendering for the application.
 */
class HtmlRenderer
{
    public function __construct(
        private readonly Config $config,
        private readonly Translator $translator,
        private readonly Router $router,
        private readonly Session $session,
        private readonly string $theme = 'windows',
    ) {
    }

    /**
     * Get available theme names.
     */
    public function getThemeNames(): array
    {
        return [
            'windows' => 'Windows',
            'mac' => 'Mac',
            'ubuntu' => 'Ubuntu'
        ];
    }

    /**
     * Render the complete HTML page.
     */
    public function renderPage(string $title, string $content): string
    {
        $errorMessage = $this->session->getErrorMessage();
        $successMessage = $this->session->getSuccessMessage();
        $notification = '';

        if ($errorMessage) {
            $errorMessage = htmlspecialchars($errorMessage);
            $notification = "<div class=\"notification-error\"><strong>Error:</strong><br />{$errorMessage}</div>";
        }

        if ($successMessage) {
            $successMessage = htmlspecialchars($successMessage);
            $notification = "<div class=\"notification-success\"><strong>Success:</strong><br />{$successMessage}</div>";
        }

        $content = $notification . $content;
        $cssFile = '/assets/css/' . $this->theme . '.css';

        // Translations for HTML
        $i18n = $this->getPageTranslations();

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="{$this->config->defaultCharset}">
            <title>{$title}</title>
            <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
            <link rel="stylesheet" href="{$cssFile}">
        </head>
        <body>
        <body>
            <div class="container">
                {$content}
            </div>
            {$this->renderPageScripts($i18n)}
            {$this->renderModals($i18n)}
        </body>
        </html>
        HTML;
    }

    /**
     * Render the login form.
     */
    public function renderLoginForm(): string
    {
        $action = $this->router->buildUrl($this->router->getCurrentPath());
        $errorMessage = $this->session->getErrorMessage();
        $errorHtml = '';
        if ($errorMessage) {
            $errorMessage = htmlspecialchars($errorMessage);
            $errorHtml = "<div class=\"notification-error\"><strong>Error:</strong> {$errorMessage}</div>";
        }
        $langSelector = $this->renderLanguageSelectorCombo();
        $themeSelector = $this->renderThemeSelectorCombo();
        $labelUser = $this->translator->translate(18);
        $labelPassword = $this->translator->translate(19);
        $labelLanguage = $this->translator->translate(20);
        $labelSubmit = $this->translator->translate(21);
        $labelTheme = $this->translator->translate(22);
        $csrfToken = htmlspecialchars($this->session->generateCsrfToken());
        
        return <<<HTML
        <div class="login-overlay">
            <div class="login-window">
                <div class="login-titlebar">SMB Web Client</div>
                <div class="login-body">
                    {$errorHtml}
                    <form method="post" action="{$action}" class="login-form">
                        <input type="hidden" name="csrf_token" value="{$csrfToken}" />
                        <input type="text" name="swcUser" placeholder="{$labelUser}" autocomplete="username" />
                        <div class="password-wrapper">
                            <input type="password" id="swcPw" name="swcPw" placeholder="{$labelPassword}" autocomplete="current-password" />
                            <span class="password-toggle" onclick="togglePassword()">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path id="eye-icon" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>
                        <div class="login-form-row">
                            {$langSelector}
                            {$themeSelector}
                        </div>
                        <div class="login-actions">
                            <input type="submit" name="swcSubmit" value="{$labelSubmit}" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
        function togglePassword() {
            const pwField = document.getElementById('swcPw');
            const eyeIcon = document.getElementById('eye-icon');
            if (pwField.type === 'password') {
                pwField.type = 'text';
                eyeIcon.setAttribute('d', 'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24');
            } else {
                pwField.type = 'password';
                eyeIcon.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z');
            }
        }
        </script>
        HTML;
    }

    /**
     * Render the server list.
     */
    public function renderServerList(array $servers): string
    {
        $logoutUrl = $this->router->buildUrl('', ['logout' => '1']);
        $logoutLink = !$this->config->allowAnonymous ? 
            '<a href="' . $logoutUrl . '" class="toolbar-link">🚪 ' . $this->translator->translate(17) . '</a>' : '';
        $toolbar = '<div class="toolbar-container">' . $logoutLink . '</div>';

        $html = '<div class="breadcrumb-toolbar-wrapper">' .
            '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>' .
            $toolbar .
            '</div>';
        
        $html .= '<table class="listing"><tr><th>' . $this->translator->translate(1) . '</th><th>' . $this->translator->translate(5) . '</th></tr>';

        foreach ($servers as $server) {
            $safeName = htmlspecialchars($server);
            $url = $this->router->buildUrl($server);
            $html .= "<tr><td><a href=\"{$url}\">{$safeName}</a></td><td>Servidor</td></tr>";
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Render the share list for a server.
     */
    public function renderShareList(string $serverName, array $shares): string
    {
        $logoutUrl = $this->router->buildUrl('', ['logout' => '1']);
        $logoutLink = !$this->config->allowAnonymous ? 
            '<a href="' . $logoutUrl . '" class="toolbar-link">🚪 ' . $this->translator->translate(17) . '</a>' : '';
        $toolbar = '<div class="toolbar-container">' . $logoutLink . '</div>';

        $html = '<div class="breadcrumb-toolbar-wrapper">' .
            '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>' .
            $toolbar .
            '</div>';
        
        $html .= '<table class="listing"><tr><th>' . $this->translator->translate(1) . '</th><th>' . $this->translator->translate(5) . '</th></tr>';
        
        foreach ($shares as $share) {
            $name = htmlspecialchars($share->getName());
            $url = $this->router->buildUrl($serverName . '/' . $name);
            $html .= "<tr><td><a href=\"{$url}\">{$name}</a></td><td>Share</td></tr>";
        }
        
        $html .= '</table>';
        return $html;
    }

    /**
     * Render a directory listing.
     * 
     * @param IFileInfo[] $items
     */
    public function renderDirectoryList(array $items): string
    {
        $action = $this->router->buildUrl($this->router->getCurrentPath());
        $actionEscaped = htmlspecialchars($action);
        $logoutUrl = $this->router->buildUrl('', ['logout' => '1']);
        $csrfToken = htmlspecialchars($this->session->generateCsrfToken());
        
        // Hidden upload form
        $html = '<form id="uploadForm" class="inline upload-form-hidden" method="post" action="' . $action . '" enctype="multipart/form-data">' .
            '<input type="hidden" name="action" value="upload" />' .
            '<input type="hidden" name="csrf_token" value="' . $csrfToken . '" />' .
            '<input type="file" name="file" multiple accept="*/*" />' .
            '</form>';
        
        // Breadcrumb + toolbar
        $logoutLink = !$this->config->allowAnonymous ? 
            '<a href="' . $logoutUrl . '" class="toolbar-link">🚪 ' . $this->translator->translate(17) . '</a>' : '';
        $toolbar = '<div class="toolbar-container">' .
            '<a href="#" id="newFolderBtn" class="toolbar-link">📁 ' . $this->translator->translate(15) . '</a>' .
            '<a href="#" id="deleteSelectedBtn" class="toolbar-link">🗑️ ' . $this->translator->translate(8) . '</a>' .
            $logoutLink .
            '</div>';

        $html .= '<div class="breadcrumb-toolbar-wrapper">' .
            '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>' .
            $toolbar .
            '</div>';

        if (count($items) > 0) {
            $html .= '<form method="post" action="' . $action . '">';
            $html .= '<input type="hidden" name="action" value="delete" />';
            $html .= '<input type="hidden" name="csrf_token" value="' . $csrfToken . '" />';
            $html .= '<table class="listing"><tr><th><input type="checkbox" id="selectAll" /></th><th></th>';
            $html .= $this->renderSortHeader(1, 'name');
            $html .= $this->renderSortHeader(2, 'size');
            $html .= $this->renderSortHeader(4, 'modified');
            $html .= $this->renderSortHeader(5, 'type');
            $html .= '<th></th></tr>';
            
            foreach ($items as $item) {
                $html .= $this->renderDirectoryItem($item);
            }
            
            $html .= '</table>';
            $html .= '</form>';
        }

        // Dropzone at the end
        $html .= '<div id="dropzone" class="dropzone"><div class="dropzone-text">📂 ' . $this->translator->translate(23) . '</div></div>';
        
        // Add action URL for JavaScript
        $html .= '<script>window.createFolderAction = "' . $actionEscaped . '"; window.csrfToken = "' . $csrfToken . '";</script>';
        
        return $html;
    }

    /**
     * Render a single directory item row.
     */
    private function renderDirectoryItem(IFileInfo $item): string
    {
        $itemName = $item->getName();
        $name = htmlspecialchars($itemName);
        $size = $item->isDirectory() ? '' : $this->formatSize($item->getSize());
        $dateFormat = $this->translator->translate(6);
        $modified = date($dateFormat, $item->getMTime());
        $path = $this->router->getCurrentPath() . '/' . $itemName;
        $url = $item->isDirectory()
            ? $this->router->buildUrl($path)
            : $this->router->buildUrl($path, ['download' => '1']);
        
        // Type: show extension for files or "Folder" for directories
        if ($item->isDirectory()) {
            $typeLabel = $this->translator->translate(11);
        } else {
            $ext = strtoupper(pathinfo($itemName, PATHINFO_EXTENSION));
            $typeLabel = $ext ? $this->translator->translate(12, $ext) : '';
        }
        
        $checkboxValue = htmlspecialchars($itemName);
        $checkbox = '<input type="checkbox" name="items[]" value="' . $checkboxValue . '" />';
        $icon = $this->getIcon($item);

        return "<tr><td>{$checkbox}</td><td>{$icon}</td><td><a href=\"{$url}\">{$name}</a></td><td>{$size}</td><td>{$modified}</td><td>{$typeLabel}</td><td></td></tr>";
    }

    /**
     * Render an error message.
     */
    public function renderError(string $message): string
    {
        return '<p>Error: ' . htmlspecialchars($message) . '</p>';
    }

    /**
     * Render the breadcrumb navigation.
     */
    public function renderBreadcrumb(): string
    {
        $rootLabel = $this->translator->translate(0);
        $rootLink = '<a href="' . $this->router->buildUrl('') . '">' . $rootLabel . '</a>';
        
        $pathParts = $this->router->getPathParts();
        if (empty($pathParts)) {
            return $rootLabel;
        }

        $parts = [];
        $accum = [];
        foreach ($pathParts as $part) {
            $accum[] = $part;
            $path = implode('/', $accum);
            $parts[] = '<a href="' . $this->router->buildUrl($path) . '">' . htmlspecialchars($part) . '</a>';
        }

        return $rootLink . ' > ' . implode(' > ', $parts);
    }

    /**
     * Render a sortable column header.
     */
    private function renderSortHeader(int $labelIndex, string $sortField): string
    {
        $sortBy = $this->router->getSortBy();
        $sortDir = $this->router->getSortDir();
        
        $nextDir = ($sortBy === $sortField && $sortDir === 'asc') ? 'desc' : 'asc';
        $sortIcon = '';

        if ($sortBy === $sortField) {
            $sortIcon = $sortDir === 'asc' ? ' ▲' : ' ▼';
        }

        $url = $this->router->buildUrl($this->router->getCurrentPath(), ['sort' => $sortField, 'dir' => $nextDir]);
        $label = $this->translator->translate($labelIndex);

        return "<th><a href=\"{$url}\" class=\"sort-link\">{$label}{$sortIcon}</a></th>";
    }

    /**
     * Render the language selector combo box.
     */
    private function renderLanguageSelectorCombo(): string
    {
        $languages = $this->translator->getAvailableLanguages();
        $currentLang = $this->translator->getLanguage();
        $label = $this->translator->translate(20);
        
        $html = '<select name="swcLang" class="login-select" aria-label="' . $label . '">';
        foreach ($languages as $code => $name) {
            $selected = $code === $currentLang ? 'selected' : '';
            $html .= '<option value="' . $code . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * Render the theme selector combo box.
     */
    private function renderThemeSelectorCombo(): string
    {
        $themes = $this->getThemeNames();
        $label = $this->translator->translate(22);
        
        $html = '<select name="swcTheme" class="login-select" aria-label="' . $label . '">';
        foreach ($themes as $code => $name) {
            $selected = $code === $this->theme ? 'selected' : '';
            $html .= '<option value="' . $code . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * Format a file size in human-readable format.
     */
    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the icon for a file or directory.
     */
    public function getIcon(IFileInfo $item): string
    {
        if ($item->isDirectory()) {
            return '📁';
        }

        $name = strtolower($item->getName());
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        return match ($ext) {
            'txt', 'md', 'log' => '📄',
            'pdf' => '📕',
            'doc', 'docx', 'rtf' => '📄',
            'xls', 'xlsx', 'csv' => '📊',
            'ppt', 'pptx' => '📊',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp' => '🖼️',
            'mp3', 'wav', 'ogg', 'flac' => '🎵',
            'mp4', 'mkv', 'mov', 'avi', 'wmv' => '🎞️',
            'zip', 'gz', 'bz2', 'xz', 'rar', '7z', 'tar', 'tgz' => '🗜️',
            'exe' => '⚙️',
            'dll' => '🧩',
            default => '📃',
        };
    }

    /**
     * Sort items by current sort settings.
     * 
     * @param IFileInfo[] $items
     * @return IFileInfo[]
     */
    public function sortItems(array $items): array
    {
        $sortBy = $this->router->getSortBy();
        $sortDir = $this->router->getSortDir();
        
        usort($items, function($a, $b) use ($sortBy, $sortDir) {
            $aDir = $a->isDirectory() ? 0 : 1;
            $bDir = $b->isDirectory() ? 0 : 1;
            
            if ($aDir !== $bDir) {
                return $aDir <=> $bDir;
            }

            $typeValue = function($item) {
                if ($item->isDirectory()) {
                    return $this->translator->translate(11);
                }

                $ext = strtoupper(pathinfo($item->getName(), PATHINFO_EXTENSION));
                return $ext ? $this->translator->translate(12, $ext) : '';
            };

            $comparison = match ($sortBy) {
                'name' => strcasecmp($a->getName(), $b->getName()),
                'size' => $a->getSize() <=> $b->getSize(),
                'modified' => $a->getMTime() <=> $b->getMTime(),
                'type' => strcasecmp($typeValue($a), $typeValue($b)),
                default => 0,
            };

            if ($comparison === 0 && $sortBy !== 'name') {
                $comparison = strcasecmp($a->getName(), $b->getName());
            }

            return $sortDir === 'desc' ? -$comparison : $comparison;
        });

        return $items;
    }

    /**
     * Get translations for page elements.
     */
    private function getPageTranslations(): array
    {
        return [
            'createFolder' => htmlspecialchars($this->translator->translate(15)),
            'folderNameLabel' => htmlspecialchars($this->translator->translate(32)),
            'folderNamePlaceholder' => htmlspecialchars($this->translator->translate(1)),
            'cancel' => htmlspecialchars($this->translator->translate(29)),
            'create' => htmlspecialchars($this->translator->translate(30)),
            'confirmDelete' => htmlspecialchars($this->translator->translate(27)),
            'delete' => htmlspecialchars($this->translator->translate(31)),
            'warning' => htmlspecialchars($this->translator->translate(24)),
            'selectAtLeastOne' => htmlspecialchars($this->translator->translate(25)),
            'close' => htmlspecialchars($this->translator->translate(26)),
            'confirmDeleteMessage' => addslashes($this->translator->translate(28, "%s")),
            'pleaseEnterFolderName' => addslashes($this->translator->translate(33)),
        ];
    }

    /**
     * Render page scripts.
     */
    private function renderPageScripts(array $i18n): string
    {
        return <<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const dropzone = document.getElementById('dropzone');
                    if (!dropzone) return;

                    const uploadForm = document.querySelector('form[enctype="multipart/form-data"]');
                    const fileInput = uploadForm ? uploadForm.querySelector('input[type="file"]') : null;

                    if (!fileInput) return;

                    function uploadFilesOneByOne(files) {
                        if (files.length === 0) {
                            location.reload();
                            return;
                        }

                        const actionUrl = uploadForm.getAttribute('action');
                        let completed = 0;
                        const total = files.length;

                        Array.from(files).forEach((file, index) => {
                            const formData = new FormData();
                            formData.append('action', 'upload');
                            formData.append('file', file);
                            if (window.csrfToken) {
                                formData.append('csrf_token', window.csrfToken);
                            }

                            fetch(actionUrl, {
                                method: 'POST',
                                body: formData
                            }).then(() => {
                                completed++;
                                if (completed === total) {
                                    location.reload();
                                }
                            }).catch(err => {
                                console.error('Upload error:', err);
                                completed++;
                                if (completed === total) {
                                    location.reload();
                                }
                            });
                        });
                    }

                    dropzone.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.add('drag-over');
                    });

                    dropzone.addEventListener('dragleave', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('drag-over');
                    });

                    dropzone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('drag-over');

                        const files = e.dataTransfer.files;
                        if (files.length > 0) {
                            uploadFilesOneByOne(files);
                        }
                    });

                    dropzone.addEventListener('click', () => {
                        fileInput.click();
                    });

                    fileInput.addEventListener('change', () => {
                        if (fileInput.files.length > 0) {
                            uploadFilesOneByOne(fileInput.files);
                        }
                    });
                });
            </script>

            <script>
                const i18n = {
                    confirmDeleteMessage: '{$i18n['confirmDeleteMessage']}',
                    pleaseEnterFolderName: '{$i18n['pleaseEnterFolderName']}'
                };

                if (window.csrfToken) {
                    const csrfMkdir = document.getElementById('csrfTokenMkdir');
                    const csrfDelete = document.getElementById('csrfTokenDelete');
                    if (csrfMkdir) csrfMkdir.value = window.csrfToken;
                    if (csrfDelete) csrfDelete.value = window.csrfToken;
                }

                const newFolderBtn = document.getElementById('newFolderBtn');
                const folderModal = document.getElementById('folderModal');
                const folderNameInput = document.getElementById('folderNameInput');
                const cancelFolderBtn = document.getElementById('cancelFolderBtn');
                const createFolderBtn = document.getElementById('createFolderBtn');
                const createFolderForm = document.getElementById('createFolderForm');
                const dirnameInput = document.getElementById('dirnameInput');

                const selectAllCheckbox = document.getElementById('selectAll');
                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('input[name="items[]"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    });
                }

                if (newFolderBtn) {
                    if (window.createFolderAction) {
                        createFolderForm.setAttribute('action', window.createFolderAction);
                    }

                    newFolderBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        folderModal.style.display = 'flex';
                        folderNameInput.focus();
                    });

                    cancelFolderBtn.addEventListener('click', () => {
                        folderModal.style.display = 'none';
                        folderNameInput.value = '';
                    });

                    createFolderBtn.addEventListener('click', () => {
                        const folderName = folderNameInput.value.trim();
                        if (!folderName) {
                            alert(i18n.pleaseEnterFolderName);
                            return;
                        }
                        
                        dirnameInput.value = folderName;
                        createFolderForm.submit();
                    });

                    folderNameInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            createFolderBtn.click();
                        }
                    });

                    folderModal.addEventListener('click', (e) => {
                        if (e.target === folderModal) {
                            folderModal.style.display = 'none';
                            folderNameInput.value = '';
                        }
                    });
                }

                const deleteConfirmModal = document.getElementById('deleteConfirmModal');
                const deleteConfirmForm = document.getElementById('deleteConfirmForm');
                const deleteConfirmMessage = document.getElementById('deleteConfirmMessage');
                const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
                const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
                const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
                const noSelectionModal = document.getElementById('noSelectionModal');
                const closeNoSelectionBtn = document.getElementById('closeNoSelectionBtn');

                const deleteForm = document.querySelector('form input[name="action"][value="delete"]')?.closest('form');
                
                if (deleteSelectedBtn && deleteForm) {
                    deleteSelectedBtn.addEventListener('click', (e) => {
                        const items = Array.from(deleteForm.querySelectorAll('input[name="items[]"]:checked'))
                            .map(input => input.value);
                        
                        if (items.length > 0) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const itemsList = items.join(', ');
                            deleteConfirmMessage.textContent = i18n.confirmDeleteMessage.replace('%s', itemsList);
                            deleteConfirmModal.style.display = 'flex';
                            
                            deleteConfirmForm.querySelectorAll('input[name="items[]"]').forEach(el => el.remove());
                            
                            items.forEach(item => {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'items[]';
                                input.value = item;
                                deleteConfirmForm.appendChild(input);
                            });
                            
                            deleteConfirmForm.setAttribute('action', deleteForm.getAttribute('action'));
                        } else {
                            e.preventDefault();
                            if (noSelectionModal) {
                                noSelectionModal.style.display = 'flex';
                            }
                        }
                    });
                }

                if (cancelDeleteBtn) {
                    cancelDeleteBtn.addEventListener('click', () => {
                        deleteConfirmModal.style.display = 'none';
                    });
                }

                if (confirmDeleteBtn) {
                    confirmDeleteBtn.addEventListener('click', () => {
                        deleteConfirmForm.submit();
                    });
                }

                if (closeNoSelectionBtn && noSelectionModal) {
                    closeNoSelectionBtn.addEventListener('click', () => {
                        noSelectionModal.style.display = 'none';
                    });

                    noSelectionModal.addEventListener('click', (e) => {
                        if (e.target === noSelectionModal) {
                            noSelectionModal.style.display = 'none';
                        }
                    });
                }

                if (deleteConfirmModal) {
                    deleteConfirmModal.addEventListener('click', (e) => {
                        if (e.target === deleteConfirmModal) {
                            deleteConfirmModal.style.display = 'none';
                        }
                    });
                }
            </script>
        HTML;
    }

    /**
     * Render modal dialogs.
     */
    private function renderModals(array $i18n): string
    {
        return <<<HTML
            <!-- Modal for creating folder -->
            <div id="folderModal" class="modal-overlay">
                <div class="modal-content">
                    <h3>{$i18n['createFolder']}</h3>
                    <p>{$i18n['folderNameLabel']}</p>
                    <input type="text" id="folderNameInput" placeholder="{$i18n['folderNamePlaceholder']}" />
                    <div class="modal-actions">
                        <button id="cancelFolderBtn" type="button" class="btn-cancel">{$i18n['cancel']}</button>
                        <button id="createFolderBtn" type="button" class="btn-primary">{$i18n['create']}</button>
                    </div>
                </div>
            </div>

            <!-- Hidden form for creating folder -->
            <form id="createFolderForm" method="post" class="hidden-form">
                <input type="hidden" name="action" value="mkdir" />
                <input type="hidden" name="csrf_token" id="csrfTokenMkdir" />
                <input type="hidden" name="dirname" id="dirnameInput" />
            </form>

            <!-- Modal for delete confirmation -->
            <div id="deleteConfirmModal" class="modal-overlay">
                <div class="modal-content" style="max-width: 500px;">
                    <h3 class="warning">{$i18n['confirmDelete']}</h3>
                    <p id="deleteConfirmMessage"></p>
                    <div class="modal-actions">
                        <button id="cancelDeleteBtn" type="button" class="btn-cancel">{$i18n['cancel']}</button>
                        <button id="confirmDeleteBtn" type="button" class="btn-delete">{$i18n['delete']}</button>
                    </div>
                </div>
            </div>

            <!-- Modal for no selection warning -->
            <div id="noSelectionModal" class="modal-overlay">
                <div class="modal-content">
                    <h3 class="warning">{$i18n['warning']}</h3>
                    <p id="noSelectionMessage">{$i18n['selectAtLeastOne']}</p>
                    <div class="modal-actions">
                        <button id="closeNoSelectionBtn" type="button" class="btn-cancel">{$i18n['close']}</button>
                    </div>
                </div>
            </div>

            <!-- Hidden form for delete -->
            <form id="deleteConfirmForm" method="post" class="hidden-form">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="csrf_token" id="csrfTokenDelete" />
            </form>
        HTML;
    }
}
