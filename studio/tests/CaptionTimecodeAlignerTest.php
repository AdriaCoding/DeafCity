<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionTimecodeAligner;

class CaptionTimecodeAlignerTest extends TestCase
{
    private CaptionTimecodeAligner $aligner;

    protected function setUp(): void
    {
        $this->aligner = new CaptionTimecodeAligner();
    }

    public function test_shifts_nle_hour_offset_to_video_start(): void
    {
        $cues = [
            $this->cue(3600.0, 3602.199, 'Title'),
            $this->cue(3603.199, 3605.719, 'Body'),
        ];

        $aligned = $this->aligner->align($cues);

        $this->assertEqualsWithDelta(0.0, $aligned[0]['start'], 0.0005);
        $this->assertEqualsWithDelta(2.199, $aligned[0]['end'], 0.0005);
        $this->assertEqualsWithDelta(3.199, $aligned[1]['start'], 0.0005);
        $this->assertEqualsWithDelta(5.719, $aligned[1]['end'], 0.0005);
        $this->assertSame('Title', $aligned[0]['text']);
        $this->assertSame(3600.0, $this->aligner->offsetSeconds($cues));
    }

    public function test_leaves_zero_based_cues_alone(): void
    {
        $cues = [
            $this->cue(0.0, 2.2, 'Aurora'),
            $this->cue(4.04, 7.599, 'Joke'),
        ];

        $this->assertSame(0.0, $this->aligner->offsetSeconds($cues));
        $this->assertSame($cues, $this->aligner->align($cues));
    }

    public function test_refuses_to_shift_when_content_spans_an_hour_or_more(): void
    {
        $cues = [
            $this->cue(3600.0, 3602.0, 'Start'),
            $this->cue(7200.0, 7205.0, 'Later'),
        ];

        $this->assertSame(0.0, $this->aligner->offsetSeconds($cues));
        $this->assertSame($cues, $this->aligner->align($cues));
    }

    /**
     * @return array{start: float, end: float, text: string, opaque: string, id: string}
     */
    private function cue(float $start, float $end, string $text): array
    {
        return [
            'start' => $start,
            'end' => $end,
            'text' => $text,
            'opaque' => '',
            'id' => '1',
        ];
    }
}
