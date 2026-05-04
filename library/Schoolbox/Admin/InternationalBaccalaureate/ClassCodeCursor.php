<?php

/**
 * Cursor for IB class code search results.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\API\Cursor\MapperCursor;
use Schoolbox\Collection;
use Schoolbox\External\ClassEntryMapper;
use ValueError;

class ClassCodeCursor implements ICursor
{
    public const LIMIT_MIN = 1;
    public const LIMIT_MAX = 100;
    public const LIMIT_DEFAULT = 50;

    private ICodec $codec;

    public function __construct(
        private ClassEntryMapper $classEntryMapper,
        private ?string $search,
        private int $limit,
        ?ICodec $codec = null
    ) {
        $this->codec = $codec ?? new Base64();

        if ($this->limit < self::LIMIT_MIN) {
            throw new ValueError(
                sprintf(
                    'Limit must be at least %d: %d provided',
                    self::LIMIT_MIN,
                    $this->limit
                )
            );
        }

        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(
                sprintf(
                    'Limit must be at most %d: %d provided',
                    self::LIMIT_MAX,
                    $this->limit
                )
            );
        }
    }

    public function next(Collection $collection): ?string
    {
        return $this->buildCursor()->next($collection);
    }

    public function current(Collection $collection): ?string
    {
        return $this->buildCursor()->current($collection);
    }

    public function retrieveResultCount(): ?int
    {
        return $this->buildCursor()->retrieveResultCount();
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {
        return $this->buildCursor()->retrieveResults($encodedCursor);
    }

    private function buildCursor(): MapperCursor
    {
        return new MapperCursor(
            $this->classEntryMapper,
            $this->limit,
            [
                'code' => $this->classEntryMapper::SORT_ORDER_ASC,
            ],
            $this->buildPredicateBuilder(),
            $this->codec
        );
    }

    private function buildPredicateBuilder(): Builder
    {
        $predicateBuilder = $this->classEntryMapper->getPredicateBuilder();
        $search = trim((string) $this->search);

        if ($search === '') {
            return $predicateBuilder;
        }

        $searchLike = '%' . $this->classEntryMapper->getDatabase()->getQuoter()->escapeForLike($search) . '%';

        return $predicateBuilder->any(
            $predicateBuilder
                ->emptyClone()
                ->like('name', $searchLike)
                ->like('code', $searchLike)
        );
    }
}
