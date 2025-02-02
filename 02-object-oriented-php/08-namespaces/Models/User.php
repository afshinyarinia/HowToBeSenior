<?php

namespace App\Examples\Models;

class User {
    public function __construct(
        public readonly string $name,
        public readonly string $email
    ) {}
} 