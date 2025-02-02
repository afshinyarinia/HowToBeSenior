<?php
/**
 * PHP Inheritance (PHP 8.x)
 * -----------------------
 * This lesson covers:
 * 1. Class inheritance
 * 2. Method overriding
 * 3. Abstract classes
 * 4. Final classes and methods
 * 5. Protected properties
 * 6. Parent constructor calls
 * 7. Covariant returns (PHP 7.4+)
 * 8. Constructor property promotion
 */

// Abstract base class
abstract class Vehicle {
    public function __construct(
        protected string $brand,
        protected string $model,
        protected int $year,
        protected float $price
    ) {}

    abstract public function getType(): string;

    public function getInfo(): string {
        return sprintf(
            "%s %s (%d) - $%.2f",
            $this->brand,
            $this->model,
            $this->year,
            $this->price
        );
    }

    // Method that can be overridden
    public function calculateValue(): float {
        $age = date('Y') - $this->year;
        return $this->price * (1 - ($age * 0.1));
    }
}

// Child class with constructor promotion
class Car extends Vehicle {
    public function __construct(
        string $brand,
        string $model,
        int $year,
        float $price,
        private int $doors = 4,
        private ?string $transmission = null
    ) {
        parent::__construct($brand, $model, $year, $price);
    }

    public function getType(): string {
        return 'Car';
    }

    // Override parent method
    public function getInfo(): string {
        return parent::getInfo() . " - {$this->doors} doors";
    }
}

// Final class that can't be inherited
final class ElectricCar extends Car {
    public function __construct(
        string $brand,
        string $model,
        int $year,
        float $price,
        private float $batteryCapacity,
        private int $range
    ) {
        parent::__construct($brand, $model, $year, $price);
    }

    // Covariant return type
    public function getType(): string {
        return 'Electric Car';
    }

    // Final method that can't be overridden
    final public function getBatteryInfo(): string {
        return sprintf(
            "Battery: %.1fkWh, Range: %dkm",
            $this->batteryCapacity,
            $this->range
        );
    }

    // Override with different calculation
    public function calculateValue(): float {
        $baseValue = parent::calculateValue();
        // Electric cars depreciate slower
        return $baseValue * 1.2;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When should I use abstract classes vs interfaces?
 * A: Use abstract classes when you want to share code implementation,
 *    use interfaces when you want to define a contract without implementation
 * 
 * Q2: What's the difference between protected and private?
 * A: Protected members are accessible in child classes,
 *    private members are only accessible in the declaring class
 * 
 * Q3: Why use final classes or methods?
 * A: To prevent inheritance when it could break functionality,
 *    or to enforce architectural boundaries
 * 
 * Q4: Can I override private methods?
 * A: No, private methods are not visible to child classes
 */

// Usage examples
echo "=== Inheritance Examples ===\n";

$car = new Car(
    brand: "Toyota",
    model: "Camry",
    year: 2020,
    price: 25000.00
);

$electricCar = new ElectricCar(
    brand: "Tesla",
    model: "Model 3",
    year: 2023,
    price: 45000.00,
    batteryCapacity: 75.0,
    range: 350
);

echo $car->getInfo() . "\n";
echo "Value: $" . number_format($car->calculateValue(), 2) . "\n";

echo $electricCar->getInfo() . "\n";
echo $electricCar->getBatteryInfo() . "\n";
echo "Value: $" . number_format($electricCar->calculateValue(), 2) . "\n";

/**
 * Best Practices:
 * 1. Use abstract classes to share common implementation
 * 2. Always call parent constructor when extending
 * 3. Use final when inheritance could break functionality
 * 4. Override methods only when behavior truly differs
 * 5. Keep inheritance hierarchies shallow
 * 6. Prefer composition over inheritance when possible
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Named arguments
 * 3. Union types
 * 4. Nullsafe operator
 * 5. Match expressions (where applicable)
 * 6. Readonly properties (where applicable)
 */ 