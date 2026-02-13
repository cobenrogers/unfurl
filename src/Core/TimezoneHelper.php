<?php

namespace Unfurl\Core;

use DateTime;
use DateTimeZone;

/**
 * Handles timezone conversions between UTC (database) and local time (display)
 *
 * Database storage: Always UTC
 * User display: Local timezone (default: America/Chicago)
 */
class TimezoneHelper
{
    private string $localTimezone;
    private DateTimeZone $utcZone;
    private DateTimeZone $localZone;

    public function __construct(string $localTimezone = 'America/Chicago')
    {
        $this->localTimezone = $localTimezone;
        $this->utcZone = new DateTimeZone('UTC');
        $this->localZone = new DateTimeZone($this->localTimezone);
    }

    /**
     * Convert local DateTime to UTC string for database storage
     *
     * @param DateTime $localDateTime DateTime in local timezone
     * @return string UTC timestamp in 'Y-m-d H:i:s' format
     */
    public function toUtc(DateTime $localDateTime): string
    {
        $utcDateTime = clone $localDateTime;
        $utcDateTime->setTimezone($this->utcZone);
        return $utcDateTime->format('Y-m-d H:i:s');
    }

    /**
     * Convert UTC string from database to local DateTime
     *
     * @param string $utcString UTC timestamp from database
     * @return DateTime DateTime object in local timezone
     */
    public function toLocal(string $utcString): DateTime
    {
        $utcDateTime = new DateTime($utcString, $this->utcZone);
        $utcDateTime->setTimezone($this->localZone);
        return $utcDateTime;
    }

    /**
     * Get current UTC timestamp for database storage
     *
     * @return string Current UTC time in 'Y-m-d H:i:s' format
     */
    public function nowUtc(): string
    {
        $now = new DateTime('now', $this->utcZone);
        return $now->format('Y-m-d H:i:s');
    }

    /**
     * Convert UTC string to formatted local time string
     *
     * @param string $utcString UTC timestamp from database
     * @param string $format PHP date format (default: 'Y-m-d H:i:s')
     * @return string Formatted local time string
     */
    public function formatLocal(string $utcString, string $format = 'Y-m-d H:i:s'): string
    {
        $localDateTime = $this->toLocal($utcString);
        return $localDateTime->format($format);
    }

    /**
     * Get the configured local timezone
     *
     * @return string Timezone identifier (e.g., 'America/Chicago')
     */
    public function getLocalTimezone(): string
    {
        return $this->localTimezone;
    }
}
