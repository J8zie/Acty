<?php

// Tests for Capture Time Rule Test.

namespace Tests\Feature;

use App\Rules\CaptureTime;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaptureTimeRuleTest extends TestCase
{
    public static function times(): array
    {

        return [
            ['live', 0, true], ['live', 9, true], ['live', 11, false], ['live', 1440, false],
            ['live', -1, true], ['live', -5, false],
            ['camera-app', 2, true], ['camera-app', 4, false],
            [null, 9, true], [null, 11, false],
        ];
    }

    #[DataProvider('times')]
    public function test_it(?string $method, int $minutesAgo, bool $valid): void
    {
        $now = Carbon::parse('2026-11-18 12:00:00', 'UTC');
        $failed = null;

        (new CaptureTime($method, $now))->validate('photo_captured_at', $now->copy()->subMinutes($minutesAgo)->toISOString(), function ($message) use (&$failed) { $failed = $message; });

        $this->assertSame($valid, $failed === null, "$method $minutesAgo min: ".$failed);
    }

    public function test_garbage_is_refused(): void
    {
        $failed = null;
        (new CaptureTime)->validate('x', 'not a date at all', function ($m) use (&$failed) { $failed = $m; });
        $this->assertSame('The :attribute must be a valid date and time.', $failed);
    }
}
