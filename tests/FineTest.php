<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\Fine;
use PHPUnit\Framework\TestCase;

final class FineTest extends TestCase
{
    public function test_a_fine_is_20_cents_per_day_late(): void
    {
        $this->assertSame(20, Fine::computeFine(1));
        $this->assertSame(500, Fine::computeFine(25));
    }

    public function test_a_fine_is_capped_at_10_euro_per_loan(): void
    {
        $this->assertSame(1000, Fine::computeFine(50));
        $this->assertSame(1000, Fine::computeFine(51));
        $this->assertSame(1000, Fine::computeFine(365));
    }
}
