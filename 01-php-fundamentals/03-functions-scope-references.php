<?php

// Function with typed parameters and return type
function add(int $a, int $b): int {
    return $a + $b;
}

// Arrow function (introduced in PHP 7.4)
$multiply = fn(int $a, int $b) => $a * $b;

// Named arguments (introduced in PHP 8.0)
function greet(string $name, string $greeting = "Hello") {
    echo "$greeting, $name!\n";
}

// Null safe operator (introduced in PHP 8.0)
$person = null;
$name = $person?->name;

// Match expression (introduced in PHP 8.0)
$status = 'active';
$result = match ($status) {
    'active' => 'User is active',
    'inactive' => 'User is inactive',
    default => 'Unknown status',
};

// Scope
$x = 10;

function scopeExample($x) {
    echo "Inside function: " . $x . "\n";
}

// References
$a = 10;
$b = &$a;
$b = 20;
echo "a: " . $a . ", b: " . $b . "\n";

// Output
echo add(5, 3) . "\n";
echo $multiply(4, 6) . "\n";
greet("John");
greet("Jane", greeting: "Hi");
echo $result . "\n";
scopeExample($x);

function incrementByReference(&$value) {
    $value++;
}

$num = 5;
incrementByReference($num);
echo $num; // Output: 6 