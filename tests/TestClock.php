<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * One clock shared by all services in a test; the test moves it with travelTo().
 */
final class TestClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function travelTo(string $date): void
    {
        $this->now = new DateTimeImmutable($date);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
