<?php

declare(strict_types=1);

namespace SmbWebClient;

class Translator
{
    private array $strings = [
        'es' => [
            'Red Windows',
            'Nombre',
            'Tamaño',
            'Comentarios',
            'Modificado',
            'Tipo',
            'd/m/Y H:i',
            'Imprimir un archivo',
            'Borrar elementos seleccionados',
            'Nuevo archivo (subir)',
            'Cancelar trabajos seleccionados',
            'Carpeta',
            'Archivo %s',
            'Enviar un mensaje',
            'Subir',
            'Nueva carpeta',
            'Descargar esta carpeta',
            'Desconectar',
        ],
        'en' => [
            'Windows Network',
            'Name',
            'Size',
            'Comments',
            'Modified',
            'Type',
            'm/d/Y h:i',
            'Print a file',
            'Delete selected items',
            'New file (upload)',
            'Cancel selected jobs',
            'Folder',
            'File %s',
            'Send a message',
            'Upload',
            'New folder',
            'Download this folder',
            'Logout',
        ],
        'fr' => [
            'Réseau Windows',
            'Nom',
            'Taille',
            'Commentaire',
            'Modifié',
            'Type',
            'd/m/Y h:i',
            'Imprimer',
            'Effacer la sélection',
            'Nouveau fichier (envoi)',
            'Annuler la sélection',
            'Dossier',
            'Fichier %s',
            'Envoyer un message popup',
            'Remonter',
            'Nouveau dossier',
            'Télécharger ce dossier',
            'Déconnexion',
        ],
    ];

    public function __construct(
        private readonly string $language,
    ) {
    }

    public function translate(int $index, ...$args): string
    {
        $string = $this->strings[$this->language][$index] 
            ?? $this->strings['en'][$index] 
            ?? "String $index";
        
        if (!empty($args)) {
            return sprintf($string, ...$args);
        }
        
        return $string;
    }

    public function detectLanguage(string $default = 'en'): string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        foreach (explode(',', $acceptLanguage) as $lang) {
            $lang = strtolower(trim(explode(';', $lang)[0]));
            $lang = explode('-', $lang)[0];
            
            if (isset($this->strings[$lang])) {
                return $lang;
            }
        }
        
        return $default;
    }
}
