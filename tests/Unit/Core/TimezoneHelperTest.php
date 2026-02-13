<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\TimezoneHelper;
use DateTime;
use DateTimeZone;

/**
 * @covers \Unfurl\Core\TimezoneHelper
 */
class TimezoneHelperTest extends TestCase
{
    private TimezoneHelper $timezone;

    protected function setUp(): void
    {
        // Use America/Chicago for testing (CST/CDT)
        $this->timezone = new TimezoneHelper('America/Chicago');
    }

    public function testToUtcConvertsLocalToUtc(): void
    {
        // January 1, 2024 12:00 PM CST (UTC-6)
        $localDateTime = new DateTime('2024-01-01 12:00:00', new DateTimeZone('America/Chicago'));
        $utcString = $this->timezone->toUtc($localDateTime);

        // Should be 6 hours ahead in UTC
        $this->assertEquals('2024-01-01 18:00:00', $utcString);
    }

    public function testToLocalConvertsUtcToLocal(): void
    {
        $utcString = '2024-01-01 18:00:00';
        $localDateTime = $this->timezone->toLocal($utcString);

        // Should be 6 hours behind in CST
        $this->assertEquals('2024-01-01 12:00:00', $localDateTime->format('Y-m-d H:i:s'));
    }

    public function testNowUtcReturnsCurrentUtcTime(): void
    {
        $before = new DateTime('now', new DateTimeZone('UTC'));
        $nowUtc = $this->timezone->nowUtc();
        $after = new DateTime('now', new DateTimeZone('UTC'));

        // Parse the string
        $nowUtcDateTime = new DateTime($nowUtc, new DateTimeZone('UTC'));

        // Should be between before and after (within 1 second tolerance)
        $this->assertGreaterThanOrEqual($before->getTimestamp() - 1, $nowUtcDateTime->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp() + 1, $nowUtcDateTime->getTimestamp());
    }

    public function testFormatLocalWithDefaultFormat(): void
    {
        $utcString = '2024-01-01 18:00:00';
        $formatted = $this->timezone->formatLocal($utcString);

        // Default format is 'Y-m-d H:i:s'
        $this->assertEquals('2024-01-01 12:00:00', $formatted);
    }

    public function testFormatLocalWithCustomFormat(): void
    {
        $utcString = '2024-01-01 18:00:00';
        $formatted = $this->timezone->formatLocal($utcString, 'Y-m-d');

        $this->assertEquals('2024-01-01', $formatted);
    }

    public function testGetLocalTimezone(): void
    {
        $this->assertEquals('America/Chicago', $this->timezone->getLocalTimezone());
    }

    public function testDefaultTimezoneIsAmericaChicago(): void
    {
        $defaultTimezone = new TimezoneHelper();
        $this->assertEquals('America/Chicago', $defaultTimezone->getLocalTimezone());
    }

    public function testRoundTripConversion(): void
    {
        // Create a local time
        $originalLocal = new DateTime('2024-06-15 14:30:00', new DateTimeZone('America/Chicago'));

        // Convert to UTC
        $utcString = $this->timezone->toUtc($originalLocal);

        // Convert back to local
        $convertedLocal = $this->timezone->toLocal($utcString);

        // Should match original
        $this->assertEquals(
            $originalLocal->format('Y-m-d H:i:s'),
            $convertedLocal->format('Y-m-d H:i:s')
        );
    }

    public function testHandlesDaylightSavingTime(): void
    {
        // June (CDT, UTC-5)
        $summerLocal = new DateTime('2024-06-01 12:00:00', new DateTimeZone('America/Chicago'));
        $summerUtc = $this->timezone->toUtc($summerLocal);
        $this->assertEquals('2024-06-01 17:00:00', $summerUtc); // Only 5 hours difference

        // January (CST, UTC-6)
        $winterLocal = new DateTime('2024-01-01 12:00:00', new DateTimeZone('America/Chicago'));
        $winterUtc = $this->timezone->toUtc($winterLocal);
        $this->assertEquals('2024-01-01 18:00:00', $winterUtc); // 6 hours difference
    }

    public function testCustomTimezone(): void
    {
        $pacificTimezone = new TimezoneHelper('America/Los_Angeles');

        // January 1, 2024 12:00 PM PST (UTC-8)
        $localDateTime = new DateTime('2024-01-01 12:00:00', new DateTimeZone('America/Los_Angeles'));
        $utcString = $pacificTimezone->toUtc($localDateTime);

        $this->assertEquals('2024-01-01 20:00:00', $utcString);
    }
}
