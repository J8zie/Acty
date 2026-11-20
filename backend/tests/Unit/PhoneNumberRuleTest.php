<?php

// Tests for Phone Number Rule Test.

namespace Tests\Unit;

use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberRuleTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            ['0912 345 6789', true], ['+63 912 345 6789', true], ['09123456789', true], ['(082) 221-1234', true],
            ['082.221.1234', true], ['1234567', true], ['+123456789012345', true],
            ['123456', false], ['1234567890123456', false], ['abc', false], ['0912-abc-6789', false], ['', false],
            ['+', false], ['--1234567', false], ['12 34 56 78 <script>', false], ['++6391234567', false],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it(string $number, bool $valid): void
    {
        $failed = false;
        (new PhoneNumber)->validate('phone', $number, function () use (&$failed) { $failed = true; });

        $this->assertSame($valid, ! $failed, "$number");
    }
}
