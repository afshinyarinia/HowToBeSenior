<?php

namespace App\Examples;

use DateTime;
use App\Examples\Models\{User, Product};
use App\Examples\Services\UserService;
use App\Examples\Interfaces\Repository;
use InvalidArgumentException;
use Throwable;

class AutoloadExample {
    private array $items = [];
    
    public function __construct(
        private readonly UserService $userService,
        private readonly ?Repository $repository = null,
    ) {}

    public function addItem(string $name, mixed $value): void {
        $this->items[$name] = [
            'value' => $value,
            'added_at' => new DateTime(),
        ];
    }

    public function getItem(string $name): mixed {
        return $this->items[$name]['value'] ?? throw new InvalidArgumentException("Item not found: $name");
    }
} 