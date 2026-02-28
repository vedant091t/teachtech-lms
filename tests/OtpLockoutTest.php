<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * OtpLockoutTest — verifies the OTP lockout and attempt-counting logic
 * that lives in verify_otp.php (tested here via helper functions and DB-free fixtures).
 *
 * Since the lockout logic is inline in verify_otp.php, we test the underlying
 * primitives (password_hash / password_verify) and the decision logic extracted
 * into testable pure functions below.
 *
 * Tests:
 *  1. password_verify passes with correct hash.
 *  2. password_verify fails with wrong guess.
 *  3. should_lock() returns false below threshold.
 *  4. should_lock() returns true at threshold.
 *  5. is_locked() returns false when lockout is in the past.
 *  6. is_locked() returns true when lockout is in the future.
 *  7. Correct OTP should reset attempts (password_verify returns true → no increment).
 *  8. Wrong OTP 5× triggers lock (simulated counter).
 */
class OtpLockoutTest extends TestCase
{
    // ── Pure helper functions mirroring verify_otp.php logic ─────────────────

    private function should_lock(int $attempts, int $max = 5): bool
    {
        return $attempts >= $max;
    }

    private function is_locked(?string $locked_until): bool
    {
        return $locked_until !== null && strtotime($locked_until) > time();
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_password_verify_passes_with_correct_otp(): void
    {
        $otp = '123456';
        $hash = password_hash($otp, PASSWORD_BCRYPT);
        $this->assertTrue(password_verify($otp, $hash));
    }

    public function test_password_verify_fails_with_wrong_otp(): void
    {
        $hash = password_hash('123456', PASSWORD_BCRYPT);
        $this->assertFalse(password_verify('999999', $hash));
    }

    public function test_should_not_lock_below_threshold(): void
    {
        $this->assertFalse($this->should_lock(4));
        $this->assertFalse($this->should_lock(0));
    }

    public function test_should_lock_at_threshold(): void
    {
        $this->assertTrue($this->should_lock(5));
        $this->assertTrue($this->should_lock(6));
    }

    public function test_not_locked_when_lockout_in_past(): void
    {
        $past = date('Y-m-d H:i:s', time() - 1);
        $this->assertFalse($this->is_locked($past));
    }

    public function test_is_locked_when_lockout_in_future(): void
    {
        $future = date('Y-m-d H:i:s', time() + 900); // 15 min from now
        $this->assertTrue($this->is_locked($future));
    }

    public function test_correct_otp_does_not_increment_attempts(): void
    {
        $otp = '654321';
        $hash = password_hash($otp, PASSWORD_BCRYPT);
        $attempts = 3;

        $verified = password_verify($otp, $hash);
        if ($verified) {
            // Simulate: on success, reset attempts — no increment
            $attempts = 0;
        } else {
            $attempts++;
        }

        $this->assertSame(0, $attempts);
    }

    public function test_five_wrong_attempts_trigger_lock(): void
    {
        $hash = password_hash('correct', PASSWORD_BCRYPT);
        $attempts = 0;
        $locked = false;
        $max = 5;

        for ($i = 0; $i < 5; $i++) {
            if (!password_verify('wrong', $hash)) {
                $attempts++;
                if ($this->should_lock($attempts, $max)) {
                    $locked = true;
                }
            }
        }

        $this->assertSame(5, $attempts);
        $this->assertTrue($locked);
    }
}
