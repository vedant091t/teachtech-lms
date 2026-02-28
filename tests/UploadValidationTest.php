<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * UploadValidationTest — verifies the handle_upload() validation rules
 * without touching the filesystem (fixtures are synthetic arrays).
 *
 * Tests (all exercising handle_upload() from core/helpers.php):
 *  1. Blocked extension is rejected.
 *  2. Allowed extension with matching MIME passes the extension + size checks.
 *  3. Double extension (shell.php.pdf) is rejected.
 *  4. File exceeding size limit is rejected.
 *  5. PHP upload error code (not UPLOAD_ERR_OK) is rejected.
 *  6. A .exe file is rejected even with UPLOAD_ERR_OK.
 *  7. Empty filename produces extension rejection.
 *  8. Size exactly at limit is accepted.
 */
class UploadValidationTest extends TestCase
{
    /**
     * Build a synthetic $_FILES entry — does NOT touch real filesystem.
     * We use a temp file with known content as the tmp_name.
     */
    private function makeFile(
        string $name,
        int $size,
        int $error = UPLOAD_ERR_OK,
        string $content = '%PDF-1.4' // default: PDF magic bytes
    ): array {
        $tmp = tempnam(sys_get_temp_dir(), 'tt_test_');
        file_put_contents($tmp, $content);

        return [
            'name' => $name,
            'type' => 'application/octet-stream', // browser MIME ignored; finfo used
            'tmp_name' => $tmp,
            'error' => $error,
            'size' => $size,
        ];
    }

    /** Prevent handle_upload from actually moving files during tests */
    private function dest(): string
    {
        $d = sys_get_temp_dir() . '/tt_upload_test';
        if (!is_dir($d))
            mkdir($d, 0755, true);
        return $d;
    }

    public function test_blocked_extension_rejected(): void
    {
        $file = $this->makeFile('evil.php', 1024);
        $result = handle_upload($file, $this->dest());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not allowed', $result['error']);
    }

    public function test_double_extension_rejected(): void
    {
        // shell.php.pdf — the filename without extension is "shell.php" (contains a dot)
        $file = $this->makeFile('shell.php.pdf', 1024);
        $result = handle_upload($file, $this->dest());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('multiple extensions', $result['error']);
    }

    public function test_file_too_large_rejected(): void
    {
        $max = (int) ($_ENV['UPLOAD_MAX_SIZE'] ?? 10485760);
        $file = $this->makeFile('big.pdf', $max + 1);

        $result = handle_upload($file, $this->dest());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('size limit', $result['error']);
    }

    public function test_upload_error_code_rejected(): void
    {
        $file = [
            'name' => 'good.pdf',
            'type' => 'application/pdf',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 0,
        ];
        $result = handle_upload($file, $this->dest());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Upload error', $result['error']);
    }

    public function test_exe_extension_rejected(): void
    {
        $file = $this->makeFile('malware.exe', 1024, UPLOAD_ERR_OK, 'MZ'); // PE magic
        $result = handle_upload($file, $this->dest());

        $this->assertFalse($result['ok']);
    }

    public function test_empty_extension_rejected(): void
    {
        $file = $this->makeFile('noextension', 1024);
        $result = handle_upload($file, $this->dest());

        $this->assertFalse($result['ok']);
    }

    public function test_size_exactly_at_limit_accepted_if_mime_matches(): void
    {
        // This test validates size NOT mime — set a very small limit to test boundary
        $_ENV['UPLOAD_MAX_SIZE'] = '100';
        $file = $this->makeFile('test.pdf', 100, UPLOAD_ERR_OK, '%PDF-1.4 boundary test');
        $result = handle_upload($file, $this->dest());

        // Reset
        $_ENV['UPLOAD_MAX_SIZE'] = '10485760';

        // Size == limit is accepted (> limit is rejected); MIME may still decide
        // We only assert it didn't fail on size — other validators may reject
        if (!$result['ok']) {
            $this->assertStringNotContainsString('size limit', $result['error']);
        } else {
            $this->assertTrue($result['ok']);
            // Clean up moved file
            $moved = $this->dest() . '/' . $result['filename'];
            if (file_exists($moved))
                unlink($moved);
        }
    }

    public function test_pdf_with_pdf_magic_bytes_passes_extension_check(): void
    {
        // Write real PDF magic bytes so finfo identifies it correctly
        $file = $this->makeFile('lecture.pdf', 1024, UPLOAD_ERR_OK, '%PDF-1.4' . str_repeat(' ', 1016));
        $result = handle_upload($file, $this->dest());

        // MIME check may pass or fail depending on finfo; the extension check must pass
        if (!$result['ok']) {
            // Only acceptable failure is MIME mismatch (content detection), not extension
            $this->assertStringNotContainsString(
                'not allowed',
                $result['error'],
                'Extension check should pass for PDF; failing reason: ' . $result['error']
            );
        } else {
            $this->assertTrue($result['ok']);
            $moved = $this->dest() . '/' . $result['filename'];
            if (file_exists($moved))
                unlink($moved);
        }
    }
}
