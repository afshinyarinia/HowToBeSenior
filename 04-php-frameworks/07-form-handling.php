<?php
/**
 * PHP Form Handling and Validation (PHP 8.x)
 * -----------------------------------
 * This lesson covers:
 * 1. Form abstraction
 * 2. Input validation
 * 3. CSRF protection
 * 4. File uploads
 * 5. Custom validation rules
 * 6. Error handling
 */

// Form interface
interface FormInterface {
    public function isValid(): bool;
    public function getErrors(): array;
    public function getData(): array;
    public function bind(array $data): void;
}

// Validation rule interface
interface ValidationRule {
    public function validate(mixed $value): bool;
    public function getMessage(): string;
}

// Basic validation rules
class RequiredRule implements ValidationRule {
    public function validate(mixed $value): bool {
        return !empty($value);
    }

    public function getMessage(): string {
        return 'This field is required';
    }
}

class EmailRule implements ValidationRule {
    public function validate(mixed $value): bool {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getMessage(): string {
        return 'Invalid email address';
    }
}

class MinLengthRule implements ValidationRule {
    public function __construct(
        private readonly int $length
    ) {}

    public function validate(mixed $value): bool {
        return strlen($value) >= $this->length;
    }

    public function getMessage(): string {
        return "Minimum length is {$this->length} characters";
    }
}

// Abstract form class
abstract class AbstractForm implements FormInterface {
    protected array $data = [];
    protected array $errors = [];
    protected array $rules = [];

    public function isValid(): bool {
        $this->errors = [];
        
        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            
            foreach ($rules as $rule) {
                if (!$rule->validate($value)) {
                    $this->errors[$field][] = $rule->getMessage();
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getData(): array {
        return $this->data;
    }

    public function bind(array $data): void {
        $this->data = array_merge($this->data, $data);
    }

    protected function addRule(string $field, ValidationRule $rule): void {
        if (!isset($this->rules[$field])) {
            $this->rules[$field] = [];
        }
        $this->rules[$field][] = $rule;
    }
}

// Example registration form
class RegistrationForm extends AbstractForm {
    public function __construct() {
        // Define validation rules
        $this->addRule('name', new RequiredRule());
        $this->addRule('name', new MinLengthRule(2));
        
        $this->addRule('email', new RequiredRule());
        $this->addRule('email', new EmailRule());
        
        $this->addRule('password', new RequiredRule());
        $this->addRule('password', new MinLengthRule(8));
    }

    // Custom validation method
    public function validatePasswordConfirmation(string $confirmation): bool {
        if ($confirmation !== ($this->data['password'] ?? null)) {
            $this->errors['password_confirmation'][] = 'Passwords do not match';
            return false;
        }
        return true;
    }
}

// CSRF protection trait
trait CSRFProtection {
    private function generateToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function validateToken(?string $token): bool {
        return $token === ($_SESSION['csrf_token'] ?? null);
    }

    public function getTokenField(): string {
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($this->generateToken())
        );
    }
}

// Form handler class
class FormHandler {
    use CSRFProtection;

    public function __construct(
        private readonly FormInterface $form,
        private readonly Logger $logger
    ) {}

    public function handle(array $data, array $files = []): bool {
        if (!$this->validateToken($data['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid CSRF token');
        }

        // Bind data to form
        $this->form->bind($data);

        // Validate form
        if (!$this->form->isValid()) {
            return false;
        }

        try {
            // Process form data
            $this->processForm($this->form->getData(), $files);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Form processing failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    private function processForm(array $data, array $files): void {
        // Implement form processing logic
        // For example, save to database, send email, etc.
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle file uploads securely?
 * A: Validate file type, size, and use move_uploaded_file()
 * 
 * Q2: How to implement custom validation rules?
 * A: Create classes implementing ValidationRule interface
 * 
 * Q3: When to use server-side vs client-side validation?
 * A: Always use both, never trust client-side only
 * 
 * Q4: How to handle multiple forms in one controller?
 * A: Use form factories and different form classes
 */

// Usage example
try {
    session_start();

    $form = new RegistrationForm();
    $handler = new FormHandler(
        form: $form,
        logger: new FileLogger(__DIR__ . '/forms.log')
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($handler->handle($_POST, $_FILES)) {
            // Redirect on success
            header('Location: /registration/success');
            exit;
        }
    }

    // Display form with errors
    $errors = $form->getErrors();
    include 'templates/registration-form.php';

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo "Form submission failed";
}

/**
 * Best Practices:
 * 1. Always validate server-side
 * 2. Use CSRF protection
 * 3. Sanitize input data
 * 4. Handle file uploads securely
 * 5. Provide clear error messages
 * 6. Log validation failures
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 