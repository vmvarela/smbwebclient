<?php

declare(strict_types=1);

namespace SmbWebClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use SmbWebClient\InputValidator;

class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InputValidator();
    }

    // ==================== sanitizePath tests ====================

    #[Test]
    public function sanitizePathRemovesLeadingAndTrailingSlashes(): void
    {
        $result = $this->validator->sanitizePath('server/share/folder');
        $this->assertSame('server/share/folder', $result);
    }

    #[Test]
    public function sanitizePathNormalizesBackslashes(): void
    {
        $result = $this->validator->sanitizePath('server\\share\\folder');
        $this->assertSame('server/share/folder', $result);
    }

    #[Test]
    public function sanitizePathThrowsOnNullBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null bytes');
        
        $this->validator->sanitizePath("server/share\0/folder");
    }

    #[Test]
    public function sanitizePathThrowsOnPathTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('traversal');
        
        $this->validator->sanitizePath('server/share/../../../etc/passwd');
    }

    #[Test]
    public function sanitizePathThrowsOnDotDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $this->validator->sanitizePath('server/..');
    }

    #[Test]
    public function sanitizePathThrowsOnSingleDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $this->validator->sanitizePath('./folder');
    }

    #[Test]
    public function sanitizePathRemovesEmptyParts(): void
    {
        $result = $this->validator->sanitizePath('server//share///folder');
        $this->assertSame('server/share/folder', $result);
    }

    #[Test]
    public function sanitizePathThrowsOnExcessiveLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maximum length');
        
        $longPath = str_repeat('a/', 3000);
        $this->validator->sanitizePath($longPath);
    }

    // ==================== sanitizeFilename tests ====================

    #[Test]
    public function sanitizeFilenameReturnsValidFilename(): void
    {
        $result = $this->validator->sanitizeFilename('document.txt');
        $this->assertSame('document.txt', $result);
    }

    #[Test]
    public function sanitizeFilenameTrimsWhitespace(): void
    {
        $result = $this->validator->sanitizeFilename('  document.txt  ');
        $this->assertSame('document.txt', $result);
    }

    #[Test]
    public function sanitizeFilenameThrowsOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        
        $this->validator->sanitizeFilename('');
    }

    #[Test]
    public function sanitizeFilenameThrowsOnWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        
        $this->validator->sanitizeFilename('   ');
    }

    #[Test]
    public function sanitizeFilenameThrowsOnNullBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null bytes');
        
        $this->validator->sanitizeFilename("file\0name.txt");
    }

    #[Test]
    #[DataProvider('forbiddenCharactersProvider')]
    public function sanitizeFilenameThrowsOnForbiddenCharacters(string $filename): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('forbidden characters');
        
        $this->validator->sanitizeFilename($filename);
    }

    public static function forbiddenCharactersProvider(): array
    {
        return [
            'forward slash' => ['file/name.txt'],
            'backslash' => ['file\\name.txt'],
            'newline' => ["file\nname.txt"],
            'carriage return' => ["file\rname.txt"],
        ];
    }

    #[Test]
    public function sanitizeFilenameThrowsOnDotDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filename');
        
        $this->validator->sanitizeFilename('..');
    }

    #[Test]
    public function sanitizeFilenameThrowsOnSingleDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filename');
        
        $this->validator->sanitizeFilename('.');
    }

    #[Test]
    public function sanitizeFilenameThrowsOnExcessiveLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maximum length');
        
        $longFilename = str_repeat('a', 300);
        $this->validator->sanitizeFilename($longFilename);
    }

    #[Test]
    public function sanitizeFilenameAllowsHiddenFiles(): void
    {
        // Hidden files (starting with .) are valid in SMB
        $result = $this->validator->sanitizeFilename('.gitignore');
        $this->assertSame('.gitignore', $result);
    }

    #[Test]
    public function sanitizeFilenameAllowsUnicodeCharacters(): void
    {
        $result = $this->validator->sanitizeFilename('документ.txt');
        $this->assertSame('документ.txt', $result);
    }

    // ==================== sanitizeFilenameList tests ====================

    #[Test]
    public function sanitizeFilenameListReturnsValidFilenames(): void
    {
        $result = $this->validator->sanitizeFilenameList(['file1.txt', 'file2.txt', 'file3.txt']);
        $this->assertSame(['file1.txt', 'file2.txt', 'file3.txt'], $result);
    }

    #[Test]
    public function sanitizeFilenameListThrowsOnInvalidFilename(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $this->validator->sanitizeFilenameList(['valid.txt', '../invalid.txt']);
    }

    #[Test]
    public function sanitizeFilenameListThrowsOnNonStringElement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filename type');
        
        $this->validator->sanitizeFilenameList(['valid.txt', 123]);
    }

    // ==================== validateSortField tests ====================

    #[Test]
    #[DataProvider('validSortFieldProvider')]
    public function validateSortFieldReturnsValidField(string $input, string $expected): void
    {
        $result = $this->validator->validateSortField($input);
        $this->assertSame($expected, $result);
    }

    public static function validSortFieldProvider(): array
    {
        return [
            'name' => ['name', 'name'],
            'size' => ['size', 'size'],
            'modified' => ['modified', 'modified'],
            'type' => ['type', 'type'],
            'invalid falls back to default' => ['invalid', 'name'],
            'empty falls back to default' => ['', 'name'],
        ];
    }

    #[Test]
    public function validateSortFieldUsesCustomDefault(): void
    {
        $result = $this->validator->validateSortField('invalid', ['a', 'b', 'c'], 'b');
        $this->assertSame('b', $result);
    }

    // ==================== validateSortDirection tests ====================

    #[Test]
    #[DataProvider('sortDirectionProvider')]
    public function validateSortDirectionReturnsValidDirection(string $input, string $expected): void
    {
        $result = $this->validator->validateSortDirection($input);
        $this->assertSame($expected, $result);
    }

    public static function sortDirectionProvider(): array
    {
        return [
            'asc' => ['asc', 'asc'],
            'desc' => ['desc', 'desc'],
            'invalid falls back to asc' => ['invalid', 'asc'],
            'empty falls back to asc' => ['', 'asc'],
            'ASC uppercase is invalid' => ['ASC', 'asc'],
        ];
    }

    // ==================== validateLanguage tests ====================

    #[Test]
    public function validateLanguageReturnsValidLanguage(): void
    {
        $result = $this->validator->validateLanguage('es', ['en', 'es', 'fr'], 'en');
        $this->assertSame('es', $result);
    }

    #[Test]
    public function validateLanguageFallsBackToDefault(): void
    {
        $result = $this->validator->validateLanguage('invalid', ['en', 'es', 'fr'], 'en');
        $this->assertSame('en', $result);
    }

    #[Test]
    public function validateLanguageStripsInvalidCharacters(): void
    {
        $result = $this->validator->validateLanguage('es<script>', ['en', 'es', 'fr'], 'en');
        // After stripping invalid chars, 'esscript' doesn't match, falls back to default
        $this->assertSame('en', $result);
    }

    // ==================== validateTheme tests ====================

    #[Test]
    public function validateThemeReturnsValidTheme(): void
    {
        $result = $this->validator->validateTheme('windows', ['windows', 'mac', 'ubuntu'], 'windows');
        $this->assertSame('windows', $result);
    }

    #[Test]
    public function validateThemeFallsBackToDefault(): void
    {
        $result = $this->validator->validateTheme('invalid', ['windows', 'mac'], 'windows');
        $this->assertSame('windows', $result);
    }

    // ==================== sanitizeUsername tests ====================

    #[Test]
    public function sanitizeUsernameReturnsValidUsername(): void
    {
        $result = $this->validator->sanitizeUsername('john.doe');
        $this->assertSame('john.doe', $result);
    }

    #[Test]
    public function sanitizeUsernameRemovesControlCharacters(): void
    {
        $result = $this->validator->sanitizeUsername("john\0doe\x1F");
        $this->assertSame('johndoe', $result);
    }

    #[Test]
    public function sanitizeUsernameTrimsWhitespace(): void
    {
        $result = $this->validator->sanitizeUsername('  john.doe  ');
        $this->assertSame('john.doe', $result);
    }

    #[Test]
    public function sanitizeUsernameTruncatesLongUsername(): void
    {
        $longUsername = str_repeat('a', 300);
        $result = $this->validator->sanitizeUsername($longUsername);
        $this->assertSame(256, strlen($result));
    }

    // ==================== validateAction tests ====================

    #[Test]
    public function validateActionReturnsValidAction(): void
    {
        $result = $this->validator->validateAction('upload', ['upload', 'delete', 'mkdir']);
        $this->assertSame('upload', $result);
    }

    #[Test]
    public function validateActionReturnsNullForInvalidAction(): void
    {
        $result = $this->validator->validateAction('invalid', ['upload', 'delete']);
        $this->assertNull($result);
    }

    #[Test]
    public function validateActionStripsInvalidCharacters(): void
    {
        $result = $this->validator->validateAction('upload<script>', ['upload', 'delete']);
        // After stripping, becomes 'uploadscript' which is not in allowed list
        $this->assertNull($result);
    }

    // ==================== isPathSafe tests ====================

    #[Test]
    #[DataProvider('safePathProvider')]
    public function isPathSafeReturnsTrueForSafePaths(string $path): void
    {
        $this->assertTrue($this->validator->isPathSafe($path));
    }

    public static function safePathProvider(): array
    {
        return [
            'simple path' => ['server/share/folder'],
            'with backslashes' => ['server\\share\\folder'],
            'single segment' => ['server'],
            'empty path' => [''],
            'root' => ['/'],
            'deep path' => ['a/b/c/d/e/f/g'],
        ];
    }

    #[Test]
    #[DataProvider('unsafePathProvider')]
    public function isPathSafeReturnsFalseForUnsafePaths(string $path): void
    {
        $this->assertFalse($this->validator->isPathSafe($path));
    }

    public static function unsafePathProvider(): array
    {
        return [
            'dot dot' => ['server/../etc'],
            'dot dot at start' => ['../server'],
            'dot dot at end' => ['server/..'],
            'null byte' => ["server\0share"],
            'backslash traversal' => ['server\\..\\etc'],
        ];
    }

    // ==================== escapeHtml tests ====================

    #[Test]
    public function escapeHtmlEscapesSpecialCharacters(): void
    {
        $result = $this->validator->escapeHtml('<script>alert("xss")</script>');
        $this->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
    }

    #[Test]
    public function escapeHtmlEscapesSingleQuotes(): void
    {
        $result = $this->validator->escapeHtml("test'value");
        // PHP 8.4+ uses &apos; while older versions use &#039;
        $this->assertTrue(
            $result === "test&#039;value" || $result === "test&apos;value",
            "Single quotes should be escaped"
        );
    }

    #[Test]
    public function escapeHtmlEscapesAmpersand(): void
    {
        $result = $this->validator->escapeHtml('a & b');
        $this->assertSame('a &amp; b', $result);
    }

    // ==================== escapeJs tests ====================

    #[Test]
    public function escapeJsEscapesSingleQuotes(): void
    {
        $result = $this->validator->escapeJs("it's a test");
        $this->assertSame("it\\'s a test", $result);
    }

    #[Test]
    public function escapeJsEscapesDoubleQuotes(): void
    {
        $result = $this->validator->escapeJs('say "hello"');
        $this->assertSame('say \\"hello\\"', $result);
    }

    #[Test]
    public function escapeJsEscapesBackslashes(): void
    {
        $result = $this->validator->escapeJs('path\\to\\file');
        $this->assertSame('path\\\\to\\\\file', $result);
    }
}
