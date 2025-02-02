<?php
/**
 * PHP Error Handling and Exceptions
 * -------------------------------
 * This lesson covers:
 * 1. Error Types in PHP
 *    - Notice: Non-critical errors (undefined variables)
 *    - Warning: More serious but non-fatal (file not found)
 *    - Fatal Error: Critical errors that stop execution
 *    - Parse Error: Syntax errors in the code
 * 
 * 2. Error Handling Methods
 *    - error_reporting(): Control which errors are reported
 *    - set_error_handler(): Custom error handling
 *    - try-catch blocks: Handle exceptions
 *    - throw: Generate exceptions
 * 
 * 3. Custom Exceptions
 *    - Creating custom exception classes
 *    - Multiple catch blocks
 *    - Finally block
 */

// Set error reporting level
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    echo "Custom Error: [$errno] $errstr\n";
    echo "Error on line $errline in $errfile\n";
    return true;
}
set_error_handler("customErrorHandler");

// Basic error generation
echo "=== Basic Error Handling ===\n";
try {
    // Generate a warning
    $file = fopen('nonexistent.txt', 'r');
} catch (Exception $e) {
    echo "Caught exception: " . $e->getMessage() . "\n";
}

// Custom Exception Classes
class DatabaseException extends Exception {
    public function __construct($message, $code = 0) {
        parent::__construct("Database Error: " . $message, $code);
    }
}

class ValidationException extends Exception {
    public function __construct($message, $code = 0) {
        parent::__construct("Validation Error: " . $message, $code);
    }
}

// Function that might throw multiple exceptions
function processUser($data) {
    if (empty($data['name'])) {
        throw new ValidationException("Name is required");
    }
    
    if (empty($data['email'])) {
        throw new ValidationException("Email is required");
    }
    
    // Simulate database error
    if (rand(0, 1) === 0) {
        throw new DatabaseException("Connection failed");
    }
    
    return "User processed successfully";
}

// Multiple exception handling
echo "\n=== Multiple Exception Handling ===\n";
try {
    $userData = [
        'name' => '',
        'email' => 'test@example.com'
    ];
    
    $result = processUser($userData);
    echo $result . "\n";
    
} catch (ValidationException $e) {
    echo "Validation failed: " . $e->getMessage() . "\n";
    // Log validation error
    
} catch (DatabaseException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    // Log database error
    
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    // Log general error
    
} finally {
    echo "This code always runs\n";
}

// Error suppression operator
echo "\n=== Error Suppression ===\n";
$value = @$undefined_variable; // @ suppresses error messages

// Throwing exceptions with custom messages
function divide($a, $b) {
    if ($b === 0) {
        throw new Exception("Division by zero!");
    }
    return $a / $b;
}

try {
    echo divide(10, 0) . "\n";
} catch (Exception $e) {
    echo "Caught exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

/**
 * Best Practices:
 * 1. Always use try-catch blocks for code that might throw exceptions
 * 2. Create specific exception classes for different types of errors
 * 3. Log errors appropriately instead of just displaying them
 * 4. Use finally blocks for cleanup code
 * 5. Avoid using the error suppression operator (@) in production
 * 6. Set appropriate error reporting levels based on environment
 * 
 * Common Use Cases:
 * - Database operations
 * - File operations
 * - API calls
 * - Form validation
 * - Configuration loading
 */ 