<?php

// If statement
$age = 18;
if ($age >= 18) {
    echo "You are an adult.\n";
} else {
    echo "You are a minor.\n";
}

// Switch statement
$day = "Monday";
switch ($day) {
    case "Monday":
        echo "Today is Monday.\n";
        break;
    case "Tuesday":
        echo "Today is Tuesday.\n";
        break;
    default:
        echo "Today is neither Monday nor Tuesday.\n";
}

// For loop
for ($i = 1; $i <= 5; $i++) {
    echo "Iteration: " . $i . "\n";
}

// While loop
$count = 0;
while ($count < 3) {
    echo "Count: " . $count . "\n";
    $count++;
}

// Do-while loop
$j = 0;
do {
    echo "J: " . $j . "\n";
    $j++;
} while ($j < 3); 