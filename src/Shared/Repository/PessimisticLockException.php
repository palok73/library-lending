<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Repository;

/**
 * Thrown when a pessimistic lock cannot be acquired on an aggregate.
 */
class PessimisticLockException extends \RuntimeException {
    public function __construct(
        string $message = "Could not acquire lock on aggregate",
        private string $aggregateId = "",
        ?\Throwable $previous
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getAggregateId(): string {
        return $this->aggregateId;
    }
}
