<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Repository;

/**
 * In-memory Unit of Work with pessimistic locking support.
 * Tracks which aggregates are locked during a transaction.
 */
class InMemoryUnitOfWork implements UnitOfWork {
    private bool $inTransaction = false;
    private array $lockedAggregates = [];  // [aggregateId => true]
    private array $transactionLog = [];    // Log of changes for rollback

    public function beginTransaction(): void {
        if ($this->inTransaction) {
            throw new \RuntimeException("Transaction already in progress");
        }
        $this->inTransaction = true;
        $this->lockedAggregates = [];
        $this->transactionLog = [];
    }

    public function commit(): void {
        if (!$this->inTransaction) {
            throw new \RuntimeException("No active transaction to commit");
        }
        // Release all locks and clear transaction log
        $this->lockedAggregates = [];
        $this->transactionLog = [];
        $this->inTransaction = false;
    }

    public function rollback(): void {
        if (!$this->inTransaction) {
            throw new \RuntimeException("No active transaction to rollback");
        }
        // Release all locks and discard transaction log
        $this->lockedAggregates = [];
        $this->transactionLog = [];
        $this->inTransaction = false;
    }

    public function isInTransaction(): bool {
        return $this->inTransaction;
    }

    /**
     * Acquire a pessimistic lock on an aggregate.
     * Throws if lock is already held by another transaction.
     */
    public function acquireLock(string $aggregateId): void {
        if (!$this->inTransaction) {
            throw new \RuntimeException("Cannot acquire lock outside of transaction");
        }
        
        if (isset($this->lockedAggregates[$aggregateId])) {
            // Lock already held by this transaction
            return;
        }
        
        // In a real DB, this would block/wait; for in-memory, we just track it
        $this->lockedAggregates[$aggregateId] = true;
    }

    /**
     * Check if lock is held for this aggregate in current transaction.
     */
    public function isLocked(string $aggregateId): bool {
        return isset($this->lockedAggregates[$aggregateId]);
    }

    /**
     * Record a change in the transaction log (for debugging/audit).
     */
    public function recordChange(string $aggregateId, string $operation): void {
        if (!$this->inTransaction) {
            throw new \RuntimeException("Cannot record changes outside of transaction");
        }
        $this->transactionLog[] = [
            'aggregateId' => $aggregateId,
            'operation' => $operation,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get transaction log (for testing)
     */
    public function getTransactionLog(): array {
        return $this->transactionLog;
    }
}
