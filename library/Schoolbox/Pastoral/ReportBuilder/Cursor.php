<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;
use ValueError;

class Cursor implements ICursor
{
    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 20 results is a sensible maximum.
     */
    public const LIMIT_MAX = 20;

    /**
     * A sensible default number of results to return from the cursor at once:
     * IMO the maximum is a sensible default.
     */
    public const LIMIT_DEFAULT = self::LIMIT_MAX;

    public function __construct(
        protected Helper $helper,
        protected ModelToArray $userTranslator,
        protected UserMapper $userMapper,
        protected TermMapper $termMapper,
        protected PastoralMapper $pastoralMapper,
        protected string $resultType,
        protected ?int $limit = self::LIMIT_DEFAULT,
        protected ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(sprintf('Limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit));
        }
    }

    public function next(Collection $collection): ?string
    {
        // TODO: Implement next() method.
        return null;
    }

    public function current(Collection $collection): ?string
    {
        // TODO: Implement current() method.
        return null;
    }

    public function retrieveResultCount(): ?int
    {
        // TODO: Implement retrieveResultCount() method.
        return null;
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {
        // TODO: Implement retrieveResults() method.
        return new Collection();
    }
}
