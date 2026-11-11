<?php

namespace Tests\Unit;

use App\Support\EnvEditor;
use PHPUnit\Framework\TestCase;

/**
 * ویرایشگر فایل .env:
 * خواندن کلید، به‌روزرسانی کلید موجود، افزودن کلید جدید و خطا برای فایل ناموجود.
 * همهٔ عملیات روی فایل موقت انجام می‌شود — .env واقعی پروژه هرگز لمس نمی‌شود.
 */
class EnvEditorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'env-editor-').'.env';
        file_put_contents($this->path, "APP_NAME=Test\nAPP_ENV=local\nAPP_DEBUG=true\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_reads_existing_key(): void
    {
        $editor = new EnvEditor($this->path);

        $this->assertSame('local', $editor->get('APP_ENV'));
        $this->assertSame('true', $editor->get('APP_DEBUG'));
        $this->assertNull($editor->get('APP_MISSING'));
        $this->assertTrue($editor->writable());
    }

    public function test_write_updates_existing_key_without_touching_others(): void
    {
        $editor = new EnvEditor($this->path);
        $editor->write('APP_ENV', 'production');

        $content = (string) file_get_contents($this->path);

        $this->assertStringContainsString('APP_ENV=production', $content);
        $this->assertStringContainsString('APP_NAME=Test', $content);
        $this->assertStringContainsString('APP_DEBUG=true', $content);
        // فقط یک خط APP_ENV
        $this->assertSame(1, substr_count($content, 'APP_ENV='));
        $this->assertSame('production', $editor->get('APP_ENV'));
    }

    public function test_write_appends_missing_key(): void
    {
        $editor = new EnvEditor($this->path);
        $editor->write('APP_KEY', 'base64:xyz');

        $content = (string) file_get_contents($this->path);

        $this->assertStringContainsString('APP_KEY=base64:xyz', $content);
        $this->assertSame('base64:xyz', $editor->get('APP_KEY'));
    }

    public function test_write_replaces_value_containing_regex_metacharacters(): void
    {
        $editor = new EnvEditor($this->path);
        $editor->write('APP_URL', 'http://localhost/$1^a.b');

        $this->assertSame('http://localhost/$1^a.b', $editor->get('APP_URL'));
        $this->assertSame('local', $editor->get('APP_ENV'));
    }

    public function test_throws_for_missing_file(): void
    {
        $editor = new EnvEditor('/nonexistent-path/.env');

        $this->assertFalse($editor->writable());

        $this->expectException(\RuntimeException::class);
        $editor->write('APP_ENV', 'production');
    }
}
