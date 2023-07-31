<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Cursor;

use Alaress\Codec\ICodec;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Search\Api\Filter\DataObject as FilterDataObject;
use Schoolbox\Search\Api\ISource;
use Schoolbox\Search\Api\Result\DataObject as ResultDataObject;

class Cursor implements ICursor
{
    public function __construct(
        private ISource $source,
        private string $keyword,
        private FilterDataObject $filter,
        private int $limit,
        private ?ICodec $codec = null,
    ) {
        $this->codec ??= new Codec();
    }

    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        // Find the last item in the collection: get a pointer to that
        // Expect `$last` to be a Result data object
        return $this->codec->encode($collection->last());
    }

    public function current(Collection $collection): ?string
    {
        return null;
    }

    public function retrieveResultCount(): ?int
    {
        // null for now
        return null;
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {
        /** @var DataObject|null $cursor */
        $cursor = $this->codec->decode($encodedCursor);
        return new GenericCollection(
            ResultDataObject::class,
            $this->source->search(
                $this->keyword,
                $this->filter,
                $cursor,
                $this->limit,
            )
        );
    }
}
