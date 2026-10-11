<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Repository;

use LibraryLanding\Shared\Domain\Title;

/**
 * In-memory Title repository with pessimistic locking.
 * Acquires locks when loading aggregates for update.
 */
class InMemoryTitleRepository implements TitleRepository {
    /** @var array<string, Title> */
    private array $store = [];
    
    /** @var array<string, bool> Track which aggregates are locked */
    private array $lockedByOtherTransaction = [];

    public function __construct(
        private UnitOfWork $unitOfWork
    ) {}

    /**
     * Load a Title without lock (read-only).
     * Returns null if not found.
     */
    public function findById(string $titleId): ?Title {
        return $this->store[$titleId] ?? null;
    }

    /**
     * Load a Title FOR UPDATE (acquire pessimistic lock).
     * Used when the aggregate will be modified in a transaction.
     * Throws if lock cannot be acquired.
     */
    public function findByIdForUpdate(string $titleId): ?Title {
        if (!$this->unitOfWork->isInTransaction()) {
            throw new \RuntimeException(
                "findByIdForUpdate() requires an active transaction"
            );
        }

        // Check if lock is already held by another transaction (simulated conflict)
        if (isset($this->lockedByOtherTransaction[$titleId])) {
            throw new PessimisticLockException(
                "Title {$titleId} is locked by another transaction",
                $titleId
            );
        }

        // Acquire lock in current transaction
        $this->unitOfWork->acquireLock($titleId);
        $this->unitOfWork->recordChange($titleId, 'LOCK_ACQUIRED');

        return $this->store[$titleId] ?? null;
    }

    /**
     * Save a Title (must be called within transaction).
     * Lock is released when transaction commits.
     */
    public function save(Title $title): void {
        if (!$this->unitOfWork->isInTransaction()) {
            throw new \RuntimeException(
                "save() must be called within a transaction"
            );
        }

        $id = $title->getId();

        // Verify lock is held
        if (!$this->unitOfWork->isLocked($id)) {
            throw new \RuntimeException(
                "Cannot save Title {$id}: no lock held in current transaction"
            );
        }

        // Update store
        $this->store[$id] = $title;
        $this->unitOfWork->recordChange($id, 'SAVE');
    }

    /**
     * Load all titles (for testing/admin, no locks)
     */
    public function findAll(): array {
        return $this->store;
    }

    /**
     * Clear repository (for testing)
     */
    public function clear(): void {
        $this->store = [];
        $this->lockedByOtherTransaction = [];
    }
}
