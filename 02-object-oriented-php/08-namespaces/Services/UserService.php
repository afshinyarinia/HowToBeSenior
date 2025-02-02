<?php

namespace App\Examples\Services;

use App\Examples\Models\User;
use App\Examples\Interfaces\Repository;

class UserService {
    public function __construct(
        private readonly Repository $repository
    ) {}

    public function createUser(string $name, string $email): User {
        return new User($name, $email);
    }
} 