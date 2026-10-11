<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

abstract class Entity {
    public function __construct(private readonly string $id) {
    }

    public function getId(): string {
        return $this->id;
    }
}
