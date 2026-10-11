<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Repository;

interface UnitOfWork {
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
    
    // Pessimistic locking support
    public function isInTransaction(): bool;
    public function acquireLock(string $aggregateId): void;
    public function isLocked(string $aggregateId): bool;
    public function recordChange(string $aggregateId, string $operation): void;
}