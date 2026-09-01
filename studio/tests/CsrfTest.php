<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\Csrf;

class CsrfTest extends TestCase
{
    public function test_issue_token_generates_a_nonempty_token(): void
    {
        $session = [];

        $token = Csrf::issueToken($session);

        $this->assertNotSame('', $token);
        $this->assertSame($token, $session['csrf_token']);
    }

    public function test_issue_token_is_stable_across_calls(): void
    {
        $session = [];

        $first = Csrf::issueToken($session);
        $second = Csrf::issueToken($session);

        $this->assertSame($first, $second);
    }

    public function test_validate_accepts_the_matching_token(): void
    {
        $session = [];
        $token = Csrf::issueToken($session);

        $this->assertTrue(Csrf::validate($session, $token));
    }

    public function test_validate_rejects_a_wrong_token(): void
    {
        $session = [];
        Csrf::issueToken($session);

        $this->assertFalse(Csrf::validate($session, 'not-the-token'));
    }

    public function test_validate_rejects_when_no_token_was_issued(): void
    {
        $session = [];

        $this->assertFalse(Csrf::validate($session, 'anything'));
    }

    public function test_validate_rejects_null_and_empty_token(): void
    {
        $session = [];
        Csrf::issueToken($session);

        $this->assertFalse(Csrf::validate($session, null));
        $this->assertFalse(Csrf::validate($session, ''));
    }
}
