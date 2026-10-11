<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Repository;

use LibraryLanding\Shared\Domain\Title;

interface TitleRepository {
    /**
     * Load a Title without lock (read-only access).
     */
    public function findById(string $titleId): ?Title;
    
    /**
     * Load a Title with pessimistic lock for update.
     * Requires active transaction.
     * Throws PessimisticLockException if lock cannot be acquired.
     */
    public function findByIdForUpdate(string $titleId): ?Title;
    
    /**
     * Save a Title within current transaction.
     * Must have acquired lock via findByIdForUpdate.
     */
    public function save(Title $title): void;
}