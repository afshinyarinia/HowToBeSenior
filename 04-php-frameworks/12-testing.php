<?php
/**
 * PHP Testing (PHP 8.x)
 * ----------------
 * This lesson covers:
 * 1. Unit testing
 * 2. Integration testing
 * 3. Mocking
 * 4. Test doubles
 * 5. Assertions
 * 6. Test coverage
 */

// Example class to test
class UserService {
    public function __construct(
        private readonly UserRepository $repository,
        private readonly Validator $validator,
        private readonly Logger $logger
    ) {}

    public function createUser(array $data): User {
        // Validate input
        if (!$this->validator->validate($data, [
            'name' => 'required|min:2',
            'email' => 'required|email'
        ])) {
            throw new ValidationException('Invalid user data');
        }

        try {
            // Check if email exists
            if ($this->repository->findByEmail($data['email'])) {
                throw new ValidationException('Email already exists');
            }

            // Create user
            $user = new User(
                id: null,
                name: $data['name'],
                email: $data['email']
            );

            return $this->repository->save($user);
        } catch (Exception $e) {
            $this->logger->error('Failed to create user', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}

// Test case base class
abstract class TestCase {
    protected function setUp(): void {}
    protected function tearDown(): void {}

    protected function assertEquals(mixed $expected, mixed $actual): void {
        if ($expected !== $actual) {
            throw new AssertionError(
                "Failed asserting that '$actual' equals '$expected'"
            );
        }
    }

    protected function assertInstanceOf(string $expected, mixed $actual): void {
        if (!($actual instanceof $expected)) {
            throw new AssertionError(
                "Failed asserting that " . get_class($actual) . " is an instance of $expected"
            );
        }
    }

    protected function assertThrows(string $exception, callable $callback): void {
        try {
            $callback();
            throw new AssertionError(
                "Failed asserting that exception $exception was thrown"
            );
        } catch (Exception $e) {
            if (!($e instanceof $exception)) {
                throw new AssertionError(
                    "Failed asserting that exception " . get_class($e) . " is an instance of $exception"
                );
            }
        }
    }
}

// Mock object builder
class MockBuilder {
    private array $methods = [];

    public function __construct(
        private readonly string $className
    ) {}

    public function method(string $name): self {
        $this->methods[$name] = [];
        return $this;
    }

    public function willReturn(mixed $value): self {
        $method = array_key_last($this->methods);
        $this->methods[$method]['return'] = $value;
        return $this;
    }

    public function with(mixed ...$args): self {
        $method = array_key_last($this->methods);
        $this->methods[$method]['args'] = $args;
        return $this;
    }

    public function willThrow(string $exception): self {
        $method = array_key_last($this->methods);
        $this->methods[$method]['throw'] = $exception;
        return $this;
    }

    public function create(): object {
        return new class($this->methods) {
            private array $methods;

            public function __construct(array $methods) {
                $this->methods = $methods;
            }

            public function __call(string $name, array $args): mixed {
                if (!isset($this->methods[$name])) {
                    throw new RuntimeException("Method $name not mocked");
                }

                $mock = $this->methods[$name];

                if (isset($mock['args']) && $mock['args'] !== $args) {
                    throw new RuntimeException("Arguments don't match expectation");
                }

                if (isset($mock['throw'])) {
                    throw new $mock['throw']();
                }

                return $mock['return'] ?? null;
            }
        };
    }
}

// Example test case
class UserServiceTest extends TestCase {
    private UserService $service;
    private MockBuilder $repositoryMock;
    private MockBuilder $validatorMock;
    private MockBuilder $loggerMock;

    protected function setUp(): void {
        $this->repositoryMock = new MockBuilder(UserRepository::class);
        $this->validatorMock = new MockBuilder(Validator::class);
        $this->loggerMock = new MockBuilder(Logger::class);

        $this->service = new UserService(
            $this->repositoryMock->create(),
            $this->validatorMock->create(),
            $this->loggerMock->create()
        );
    }

    public function testCreateUserSuccess(): void {
        // Arrange
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $this->validatorMock
            ->method('validate')
            ->with($userData, [
                'name' => 'required|min:2',
                'email' => 'required|email'
            ])
            ->willReturn(true);

        $this->repositoryMock
            ->method('findByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $expectedUser = new User(1, 'John Doe', 'john@example.com');
        
        $this->repositoryMock
            ->method('save')
            ->willReturn($expectedUser);

        // Act
        $user = $this->service->createUser($userData);

        // Assert
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testCreateUserValidationFailure(): void {
        // Arrange
        $userData = [
            'name' => 'J',  // Too short
            'email' => 'invalid-email'
        ];

        $this->validatorMock
            ->method('validate')
            ->willReturn(false);

        // Assert
        $this->assertThrows(ValidationException::class, function() use ($userData) {
            $this->service->createUser($userData);
        });
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: What to test and what not to test?
 * A: Test behavior and business logic, not implementation details
 * 
 * Q2: How to handle dependencies in tests?
 * A: Use mocks, stubs, or test doubles
 * 
 * Q3: How to test private methods?
 * A: Test through public methods, or reconsider design
 * 
 * Q4: When to use integration vs unit tests?
 * A: Unit for isolated logic, integration for component interaction
 */

// Usage example
try {
    $test = new UserServiceTest();
    
    $test->setUp();
    $test->testCreateUserSuccess();
    $test->testCreateUserValidationFailure();
    $test->tearDown();

    echo "All tests passed!\n";
} catch (AssertionError $e) {
    echo "Test failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Follow AAA pattern (Arrange-Act-Assert)
 * 2. Test one thing per test
 * 3. Use meaningful test names
 * 4. Keep tests independent
 * 5. Don't test implementation details
 * 6. Maintain test coverage
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 