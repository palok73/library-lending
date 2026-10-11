<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Domain\Copy;
use PHPUnit\Framework\TestCase;

final class CopyTest extends TestCase
{
    public function test_a_copy_cannot_be_lent_twice(): void
    {
        $copy = new Copy('copy-1', 'title-1');
        $copy->markAsBorrowed();

        $this->expectException(BorrowingException::class);
        $copy->markAsBorrowed();
    }

    public function test_a_copy_on_the_shelf_cannot_be_returned(): void
    {
        $copy = new Copy('copy-1', 'title-1');

        $this->expectException(BorrowingException::class);
        $copy->markAsAvailable();
    }

    public function test_a_returned_copy_is_available_again(): void
    {
        $copy = new Copy('copy-1', 'title-1');
        $copy->markAsBorrowed();
        $copy->markAsAvailable();

        $this->assertTrue($copy->isAvailable());
    }
}
