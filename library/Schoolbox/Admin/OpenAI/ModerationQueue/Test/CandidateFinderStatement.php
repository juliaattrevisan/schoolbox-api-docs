<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue\Test;

class CandidateFinderStatement extends \PDOStatement
{
    public string $sql = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $executedParams = null;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(private readonly array $rows)
    {
    }

    /**
     * @param array<string, mixed>|null $params
     * @return bool
     */
    public function execute(?array $params = null): bool
    {
        $this->executedParams = $params;
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }
}
