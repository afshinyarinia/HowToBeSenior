<?php

// Indexed arrays
$fruits = ["apple", "banana", "orange"];
echo "Fruits: " . implode(", ", $fruits) . "\n";

// Associative arrays
$person = [
    "name" => "John Doe",
    "age" => 30,
    "city" => "New York"
];
echo "Name: " . $person["name"] . "\n";
echo "Age: " . $person["age"] . "\n";
echo "City: " . $person["city"] . "\n";

// Multidimensional arrays
$matrix = [
    [1, 2, 3],
    [4, 5, 6],
    [7, 8, 9]
];
echo "Matrix element at [1][2]: " . $matrix[1][2] . "\n";

// Array functions
$numbers = [1, 2, 3, 4, 5];
echo "Count: " . count($numbers) . "\n";
echo "Sum: " . array_sum($numbers) . "\n";
echo "Reversed: " . implode(", ", array_reverse($numbers)) . "\n";

// Array destructuring (introduced in PHP 7.1)
[$a, $b, $c] = $fruits;
echo "Destructured: $a, $b, $c\n";

// Array unpacking (introduced in PHP 7.4)
$combined = [...$fruits, ...$numbers];
echo "Combined: " . implode(", ", $combined) . "\n"; 