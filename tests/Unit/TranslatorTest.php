<?php

declare(strict_types=1);

namespace SmbWebClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use SmbWebClient\Translator;

class TranslatorTest extends TestCase
{
    #[Test]
    public function constructorSetsLanguage(): void
    {
        $translator = new Translator('en');
        $this->assertSame('en', $translator->getLanguage());
    }

    #[Test]
    public function translateReturnsCorrectStringForEnglish(): void
    {
        $translator = new Translator('en');
        
        $this->assertSame('Windows Network', $translator->translate(0));
        $this->assertSame('Name', $translator->translate(1));
        $this->assertSame('Size', $translator->translate(2));
        $this->assertSame('Folder', $translator->translate(11));
    }

    #[Test]
    public function translateReturnsCorrectStringForSpanish(): void
    {
        $translator = new Translator('es');
        
        $this->assertSame('Red Windows', $translator->translate(0));
        $this->assertSame('Nombre', $translator->translate(1));
        $this->assertSame('Tamaño', $translator->translate(2));
        $this->assertSame('Carpeta', $translator->translate(11));
    }

    #[Test]
    public function translateReturnsCorrectStringForFrench(): void
    {
        $translator = new Translator('fr');
        
        $this->assertSame('Réseau Windows', $translator->translate(0));
        $this->assertSame('Nom', $translator->translate(1));
        $this->assertSame('Dossier', $translator->translate(11));
    }

    #[Test]
    public function translateFallsBackToEnglishForMissingString(): void
    {
        // Spanish doesn't have all strings, so it should fallback to English
        $translator = new Translator('es');
        
        // Index 23 is "Drop files here to upload or click" which exists in 'en' but not 'es'
        $this->assertSame('Drop files here to upload or click', $translator->translate(23));
    }

    #[Test]
    public function translateReturnsPlaceholderForUnknownIndex(): void
    {
        $translator = new Translator('en');
        
        // Very high index that doesn't exist
        $result = $translator->translate(999);
        $this->assertSame('String 999', $result);
    }

    #[Test]
    public function translateSupportsSprintfFormatting(): void
    {
        $translator = new Translator('en');
        
        // Index 12 is "File %s"
        $result = $translator->translate(12, 'document.txt');
        $this->assertSame('File document.txt', $result);
    }

    #[Test]
    public function translateHandlesMultipleArguments(): void
    {
        $translator = new Translator('en');
        
        // Index 28 is "Are you sure you want to delete: %s? Only files and empty folders can be deleted."
        $result = $translator->translate(28, 'test.txt');
        $this->assertStringContainsString('test.txt', $result);
    }

    #[Test]
    #[DataProvider('languageProvider')]
    public function getAvailableLanguagesContainsLanguage(string $code, string $name): void
    {
        $translator = new Translator('en');
        $languages = $translator->getAvailableLanguages();
        
        $this->assertArrayHasKey($code, $languages);
        $this->assertSame($name, $languages[$code]);
    }

    public static function languageProvider(): array
    {
        return [
            ['en', 'English'],
            ['es', 'Español'],
            ['fr', 'Français'],
            ['de', 'Deutsch'],
            ['ja', '日本語'],
            ['zh', '简体中文'],
            ['ru', 'Русский'],
            ['pt-br', 'Português Brasileiro'],
        ];
    }

    #[Test]
    public function getAvailableLanguagesReturnsAllLanguages(): void
    {
        $translator = new Translator('en');
        $languages = $translator->getAvailableLanguages();
        
        // Should have at least 40 languages
        $this->assertGreaterThan(40, count($languages));
        
        // All keys should be string language codes
        foreach ($languages as $code => $name) {
            $this->assertIsString($code);
            $this->assertIsString($name);
            $this->assertNotEmpty($name);
        }
    }

    #[Test]
    public function detectLanguageReturnsDefaultWhenNoHeader(): void
    {
        // Ensure HTTP_ACCEPT_LANGUAGE is not set
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        
        $translator = new Translator('en');
        $detected = $translator->detectLanguage('es');
        
        $this->assertSame('es', $detected);
    }

    #[Test]
    public function detectLanguageDetectsFromAcceptLanguageHeader(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9,en;q=0.8';
        
        try {
            $translator = new Translator('en');
            $detected = $translator->detectLanguage('en');
            
            $this->assertSame('fr', $detected);
        } finally {
            unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
    }

    #[Test]
    public function detectLanguageFallsBackWhenLanguageNotSupported(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'xx-XX,xx;q=0.9';
        
        try {
            $translator = new Translator('en');
            $detected = $translator->detectLanguage('de');
            
            $this->assertSame('de', $detected);
        } finally {
            unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
    }

    #[Test]
    public function detectLanguageHandlesMultipleLanguages(): void
    {
        // First language not supported, second one is
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'xx;q=0.9,de;q=0.8,en;q=0.7';
        
        try {
            $translator = new Translator('en');
            $detected = $translator->detectLanguage('en');
            
            $this->assertSame('de', $detected);
        } finally {
            unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
    }

    #[Test]
    public function allLanguagesHaveBasicStrings(): void
    {
        $translator = new Translator('en');
        $languages = $translator->getAvailableLanguages();
        
        foreach (array_keys($languages) as $langCode) {
            $langTranslator = new Translator($langCode);
            
            // Every language should have at least the first string (Windows Network equivalent)
            $networkString = $langTranslator->translate(0);
            $this->assertNotSame(
                'String 0', 
                $networkString,
                "Language '$langCode' is missing the network name translation"
            );
        }
    }
}
