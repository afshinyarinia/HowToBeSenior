<?php

// String concatenation
$firstName = "John";
$lastName = "Doe";
$fullName = $firstName . " " . $lastName;
echo "Full Name: " . $fullName . "\n";

// String functions
$text = "Hello, World!";
echo "Length: " . strlen($text) . "\n";
echo "Uppercase: " . strtoupper($text) . "\n";
echo "Lowercase: " . strtolower($text) . "\n";
echo "Substring: " . substr($text, 7, 5) . "\n";

// String interpolation
$age = 30;
echo "Age: $age\n";

// Heredoc and Nowdoc syntax
$heredoc = <<<EOT
This is a heredoc string.
It can span multiple lines.
Variables are parsed: $firstName
EOT;

$nowdoc = <<<'EOT'
This is a nowdoc string.
It can also span multiple lines.
Variables are not parsed: $firstName
EOT;

echo $heredoc . "\n";
echo $nowdoc . "\n";

// Regular expressions (regex)
$email = "john.doe@example.com";
$pattern = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

if (preg_match($pattern, $email)) {
    echo "Valid email address\n";
} else {
    echo "Invalid email address\n";
}

$text = "The quick brown fox jumps over the lazy dog";
$replacedText = preg_replace("/fox/", "cat", $text);
echo "Replaced Text: " . $replacedText . "\n"; 