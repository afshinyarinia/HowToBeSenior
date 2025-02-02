<?php

namespace App\Examples\Models;

class Product {
    public function __construct(
        public readonly string $name,
        public readonly float $price
    ) {}
} 