<?php

/**
 * Cursor for IB language list results.
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
use Schoolbox\Language\Mapper as LanguageMapper;
use ValueError;

class Cursor implements ICursor
{
    public const LIMIT_MIN = 1;
    public const LIMIT_MAX = 100;
    public const LIMIT_DEFAULT = 50;

    private ICodec $codec;

    public function __construct(
        private LanguageMapper $languageMapper,
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
            $this->languageMapper,
            $this->limit,
            [
                'name' => $this->languageMapper::SORT_ORDER_ASC,
                'code' => $this->languageMapper::SORT_ORDER_ASC,
                'id' => $this->languageMapper::SORT_ORDER_ASC,
            ],
            $this->buildPredicateBuilder(),
            $this->codec
        );
    }

    private function buildPredicateBuilder(): Builder
    {
        $predicateBuilder = $this->languageMapper->getPredicateBuilder();
        $search = trim((string) $this->search);

        if ($search === '') {
            return $predicateBuilder;
        }

        $searchLike = '%' . $this->languageMapper->getDatabase()->getQuoter()->escapeForLike($search) . '%';

        return $predicateBuilder->any(
            $predicateBuilder
                ->emptyClone()
                ->like('name', $searchLike)
                ->like('code', $searchLike)
        );
    }
}
