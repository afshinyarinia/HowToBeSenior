<?php

namespace App\Examples\Usage;

use App\Examples\AutoloadExample;
use App\Examples\Services\UserService;
use App\Examples\Models\User;

class Example {
    public function example(): void {
        $service = new UserService(new class implements \App\Examples\Interfaces\Repository {
            public function find(int $id): mixed {
                return null;
            }
            public function save(mixed $entity): void {}
        });

        $example = new AutoloadExample($service);
        $example->addItem('user', new User('John Doe', 'john@example.com'));
        
        try {
            $user = $example->getItem('user');
            echo $user->name . "\n";
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
} 