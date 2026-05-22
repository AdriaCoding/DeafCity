<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionFileIntegrityChecker;

class CaptionFileIntegrityCheckerTest extends TestCase
{
    private CaptionFileIntegrityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new CaptionFileIntegrityChecker();
    }

    public function test_returns_error_for_each_overlapping_pair(): void
    {
        $cues = [
            ['start' => 0.0, 'end' => 10.0, 'text' => 'A', 'opaque' => ''],
            ['start' => 2.0, 'end' => 6.0,  'text' => 'B', 'opaque' => ''],
            ['start' => 7.0, 'end' => 12.0, 'text' => 'C', 'opaque' => ''],
        ];

        $errors = $this->checker->check($cues);

        // Cue 1 overlaps with cue 2 AND cue 3; cue 2 does not overlap cue 3
        $this->assertCount(2, $errors);
    }

    public function test_flags_cue_where_start_equals_end(): void
    {
        $cues = [
            ['start' => 2.0, 'end' => 2.0, 'text' => 'Bad', 'opaque' => ''],
        ];

        $errors = $this->checker->check($cues);

        $this->assertNotEmpty($errors);
    }

    public function test_flags_cue_where_start_exceeds_end(): void
    {
        $cues = [
            ['start' => 5.0, 'end' => 2.0, 'text' => 'Bad', 'opaque' => ''],
        ];

        $errors = $this->checker->check($cues);

        $this->assertNotEmpty($errors);
    }

    public function test_allows_adjacent_cues(): void
    {
        $cues = [
            ['start' => 0.0, 'end' => 2.0, 'text' => 'A', 'opaque' => ''],
            ['start' => 2.0, 'end' => 5.0, 'text' => 'B', 'opaque' => ''],
        ];

        $errors = $this->checker->check($cues);

        $this->assertSame([], $errors);
    }

    public function test_returns_errors_for_overlapping_cues(): void
    {
        $cues = [
            ['start' => 0.0, 'end' => 5.0, 'text' => 'A', 'opaque' => ''],
            ['start' => 3.0, 'end' => 7.0, 'text' => 'B', 'opaque' => ''],
        ];

        $errors = $this->checker->check($cues);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('1', $errors[0]);
        $this->assertStringContainsString('2', $errors[0]);
    }

    public function test_returns_empty_array_for_clean_cue_list(): void
    {
        $cues = [
            ['start' => 0.0,  'end' => 2.0,  'text' => 'Hello', 'opaque' => ''],
            ['start' => 2.0,  'end' => 5.0,  'text' => 'World', 'opaque' => ''],
        ];

        $errors = $this->checker->check($cues);

        $this->assertSame([], $errors);
    }
}
