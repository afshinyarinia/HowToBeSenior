<?php

namespace App\Examples\Interfaces;

interface Repository {
    public function find(int $id): mixed;
    public function save(mixed $entity): void;
} 