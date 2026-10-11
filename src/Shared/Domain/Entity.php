<?php

namespace LibraryLanding\Shared\Domain;

abstract class Entity {
    private string $id;

    public function getId() {
        return $this->id;
    }

    public function setId(string $id):void {
        $this->id = $id;
    }
}