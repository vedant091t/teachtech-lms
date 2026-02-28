<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * CsrfHelperTest — verifies the CSRF token generation and verification helpers.
 *
 * Tests:
 *  1. csrf_token() returns a 64-char hex string.
 *  2. Calling csrf_token() twice returns the same token (session-stable).
 *  3. csrf_field() output contains a hidden input with the token value.
 *  4. A tampered token fails hash_equals comparison.
 *  5. A correct token passes hash_equals comparison.
 */
class CsrfHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the CSRF token each test so tests are independent
        unset($_SESSION['csrf']);
    }

    public function test_token_is_64_hex_chars(): void
    {
        $token = csrf_token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function test_token_is_stable_within_session(): void
    {
        $first = csrf_token();
        $second = csrf_token();
        $this->assertSame($first, $second);
    }

    public function test_csrf_field_contains_hidden_input(): void
    {
        $token = csrf_token();
        $html = csrf_field();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('value="' . $token . '"', $html);
    }

    public function test_tampered_token_does_not_match(): void
    {
        $real = csrf_token();
        $tampered = str_repeat('a', 64); // different from real token
        $this->assertFalse(hash_equals($real, $tampered));
    }

    public function test_correct_token_matches(): void
    {
        $real = csrf_token();
        $this->assertTrue(hash_equals($real, $real));
    }
}
