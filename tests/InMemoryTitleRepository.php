<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\Title;
use LibraryLanding\Shared\Repository\TitleRepository;

final class InMemoryTitleRepository implements TitleRepository
{
    /** @var array<string, Title> */
    private array $titles = [];
    public int $saveCount = 0;

    public function findById(string $titleId): ?Title
    {
        return $this->titles[$titleId] ?? null;
    }

    public function save(Title $title): void
    {
        $this->titles[$title->getId()] = $title;
        $this->saveCount++;
    }
}
