<?php
/**
 * PHP Design Patterns: Builder (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Builder pattern
 * 2. Fluent interface
 * 3. Director class
 * 4. Complex object construction
 * 5. Immutable objects
 * 6. Validation during build
 */

// Product class
class QueryBuilder {
    private array $select = ['*'];
    private string $from;
    private array $where = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private int $offset = 0;
    private array $joins = [];
    private array $groupBy = [];
    private ?string $having = null;

    public function getSQL(): string {
        $sql = "SELECT " . implode(', ', $this->select);
        $sql .= " FROM " . $this->from;
        
        foreach ($this->joins as $join) {
            $sql .= " " . $join;
        }

        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }

        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        if ($this->having !== null) {
            $sql .= " HAVING " . $this->having;
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
            if ($this->offset > 0) {
                $sql .= " OFFSET " . $this->offset;
            }
        }

        return $sql;
    }
}

// Builder interface
interface SQLQueryBuilder {
    public function select(array $fields): self;
    public function from(string $table): self;
    public function where(string $condition): self;
    public function orderBy(string $field, string $direction = 'ASC'): self;
    public function limit(int $limit): self;
    public function getResult(): QueryBuilder;
}

// Concrete builder
class MySQLQueryBuilder implements SQLQueryBuilder {
    private QueryBuilder $query;

    public function __construct() {
        $this->reset();
    }

    public function reset(): void {
        $this->query = new QueryBuilder();
    }

    public function select(array $fields): self {
        $this->query->select = $fields;
        return $this;
    }

    public function from(string $table): self {
        $this->query->from = $table;
        return $this;
    }

    public function where(string $condition): self {
        $this->query->where[] = $condition;
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self {
        $this->query->orderBy[] = "$field $direction";
        return $this;
    }

    public function limit(int $limit): self {
        $this->query->limit = $limit;
        return $this;
    }

    public function getResult(): QueryBuilder {
        $result = $this->query;
        $this->reset();
        return $result;
    }
}

// Director class
class QueryDirector {
    private SQLQueryBuilder $builder;

    public function __construct(SQLQueryBuilder $builder) {
        $this->builder = $builder;
    }

    public function createPaginatedQuery(string $table, int $page, int $perPage): QueryBuilder {
        return $this->builder
            ->select(['*'])
            ->from($table)
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->getResult();
    }

    public function createFilteredQuery(string $table, array $conditions): QueryBuilder {
        $builder = $this->builder
            ->select(['*'])
            ->from($table);

        foreach ($conditions as $condition) {
            $builder->where($condition);
        }

        return $builder->getResult();
    }
}

// Complex object example
class EmailBuilder {
    private array $recipients = [];
    private array $cc = [];
    private array $bcc = [];
    private string $subject = '';
    private string $body = '';
    private array $attachments = [];
    private array $headers = [];
    private string $template = '';
    private array $templateVars = [];

    public function addRecipient(string $email, string $name = ''): self {
        $this->recipients[] = $name ? "$name <$email>" : $email;
        return $this;
    }

    public function addCC(string $email): self {
        $this->cc[] = $email;
        return $this;
    }

    public function addBCC(string $email): self {
        $this->bcc[] = $email;
        return $this;
    }

    public function setSubject(string $subject): self {
        $this->subject = $subject;
        return $this;
    }

    public function setBody(string $body): self {
        $this->body = $body;
        return $this;
    }

    public function addAttachment(string $path, string $name = ''): self {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?: basename($path)
        ];
        return $this;
    }

    public function setTemplate(string $template, array $vars = []): self {
        $this->template = $template;
        $this->templateVars = $vars;
        return $this;
    }

    public function addHeader(string $name, string $value): self {
        $this->headers[$name] = $value;
        return $this;
    }

    public function build(): Email {
        $this->validate();

        return new Email(
            recipients: $this->recipients,
            cc: $this->cc,
            bcc: $this->bcc,
            subject: $this->subject,
            body: $this->buildBody(),
            attachments: $this->attachments,
            headers: $this->headers
        );
    }

    private function validate(): void {
        if (empty($this->recipients)) {
            throw new InvalidArgumentException('Email must have at least one recipient');
        }

        if (empty($this->subject)) {
            throw new InvalidArgumentException('Email must have a subject');
        }

        if (empty($this->body) && empty($this->template)) {
            throw new InvalidArgumentException('Email must have a body or template');
        }
    }

    private function buildBody(): string {
        if ($this->template) {
            // Simple template processing
            $body = file_get_contents($this->template);
            foreach ($this->templateVars as $key => $value) {
                $body = str_replace("{{$key}}", $value, $body);
            }
            return $body;
        }

        return $this->body;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: Builder vs Factory?
 * A: Builder for complex objects step by step, Factory for simple creation
 * 
 * Q2: When to use Director?
 * A: When you have common construction sequences
 * 
 * Q3: How to handle validation?
 * A: Validate in build() method before creating final object
 * 
 * Q4: What about immutability?
 * A: Use readonly properties in final object
 */

// Usage example
try {
    // Query builder usage
    $queryBuilder = new MySQLQueryBuilder();
    $query = $queryBuilder
        ->select(['id', 'name', 'email'])
        ->from('users')
        ->where('status = "active"')
        ->orderBy('name')
        ->limit(10)
        ->getResult();

    echo $query->getSQL() . "\n";

    // Query director usage
    $director = new QueryDirector(new MySQLQueryBuilder());
    $paginatedQuery = $director->createPaginatedQuery('users', 1, 20);
    
    echo $paginatedQuery->getSQL() . "\n";

    // Email builder usage
    $email = (new EmailBuilder())
        ->addRecipient('user@example.com', 'John Doe')
        ->addCC('manager@example.com')
        ->setSubject('Welcome!')
        ->setTemplate('welcome.html', [
            'name' => 'John',
            'company' => 'Acme Inc'
        ])
        ->addAttachment('terms.pdf')
        ->addHeader('X-Priority', '1')
        ->build();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use fluent interface
 * 2. Validate before building
 * 3. Reset builder state
 * 4. Make final object immutable
 * 5. Use type hints
 * 6. Handle edge cases
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 