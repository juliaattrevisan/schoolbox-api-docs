<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use InvalidArgumentException;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Moment\Mapper;
use Schoolbox\Learning\Moment\Model;
use Schoolbox\User;

/**
 * Cursor for learning moments.
 */
class Cursor implements ICursor
{
    /**
     * The number of comments to return per request.
     */
    private const RESULTS_PER_REQUEST = 10;


    /**
     * The mapper class that this cursor is being built for.
     *
     * @var Mapper
     */
    private Mapper $mapper;


    /**
     * The user for which to retrieve evidence.
     *
     * @var User
     */
    private User $user;


    /**
     * The order in which to retrieve users.
     *
     * @var string
     */
    private $order;


    /**
     * The codec implementation used for the cursor.
     *
     * @var ICodec
     */
    private $codec;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     * @param User   $user
     * @param string $order
     * @throws InvalidArgumentException if $order isn't one of this class' ORDER_* constants
     */
    public function __construct(Mapper $mapper, User $user, string $order = Mapper::SORT_ORDER_DESC)
    {
        $this->mapper = $mapper;
        $this->user   = $user;

        if (!in_array($order, [Mapper::SORT_ORDER_ASC, Mapper::SORT_ORDER_DESC], true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Order must be one of the %s::SORT_ORDER_* constants',
                    Mapper::class
                )
            );
        }
        $this->order = $order;
        $this->codec = new Base64();
    }


    /**
     * Retrieve the total number of learning moments for this user, independent
     * of any cursor.
     *
     * @return int
     */
    public function retrieveResultCount(): int
    {
        return $this->mapper->countByPredicates($this->buildPredicateBuilder());
    }


    /**
     * Retrieve the page of results that $currentCursor points to.
     *
     * @param string|null $currentCursor The current encoded cursor, if there is one.
     * @return GenericCollection
     */
    public function retrieveResults(?string $currentCursor): GenericCollection
    {
        return $this->mapper->findByPredicates(
            $this->applyCursor($this->buildPredicateBuilder(), $currentCursor),
            [
                'limit' => self::RESULTS_PER_REQUEST,
                'order' => [
                    'created_at' => $this->order,
                    'id'         => $this->order,
                ],
            ]
        )->reindex();
    }


    /**
     * Retrieve a cursor to the current collection.
     *
     * This may be useful when retrieving a result set without a cursor, in
     * case the initial result set at this time needs to be fetched.
     *
     * @param Collection $collection
     * @return string|null
     */
    public function current(Collection $collection): ?string
    {
        $firstRecord = $collection->first();
        if (!$firstRecord instanceof Model) {
            return null;
        }

        // The current cursor points to one record before the first item in
        // the record set.
        // In other words, the next cursor for the previous record set.
        // Ascending:  id - 1
        // Descending: id + 1
        $cursorParts          = [
            'created_at' => $firstRecord->getCreatedAt()->getDatabase(),
        ];
        $previousPageCursorId = $firstRecord->getId();
        if ($this->order === Mapper::SORT_ORDER_ASC) {
            $previousPageCursorId--;
        } elseif ($this->order === Mapper::SORT_ORDER_DESC) {
            $previousPageCursorId++;
        }
        $cursorParts['id'] = $previousPageCursorId;
        return $this->codec->encode($cursorParts);
    }


    /**
     * Retrieves a cursor to the next collection.
     *
     * @param Collection $collection
     * @return string|null
     */
    public function next(Collection $collection): ?string
    {
        $lastRecord = $collection->last();
        if (!$lastRecord instanceof Model) {
            return null;
        }

        // The next cursor points to the last record.
        return $this->codec->encode([
            'created_at' => $lastRecord->getCreatedAt()->getDatabase(),
            'id'         => $lastRecord->getId(),
        ]);
    }


    /**
     * Apply a cursor to the predicate builder.
     *
     * @param Builder     $builder
     * @param string|null $cursor
     * @return Builder
     */
    private function applyCursor(Builder $builder, ?string $cursor): Builder
    {
        if (isset($cursor) && $cursor !== '') {
            $decoded = $this->codec->decode($cursor);
            if (isset($decoded)) {
                if ($this->order === Mapper::SORT_ORDER_ASC) {
                    // Ascending:
                    // Get items greater than last date, or:
                    // equal to last date and greater than last ID
                    $builder->any(
                        $builder
                            ->emptyClone()
                            ->greaterThan('created_at', $decoded['created_at'])
                            ->all(
                                $builder
                                    ->emptyClone()
                                    ->equals('created_at', $decoded['created_at'])
                                    ->greaterThan('id', $decoded['id'])
                            )
                    );
                } elseif ($this->order === Mapper::SORT_ORDER_DESC) {
                    // Descending:
                    // Get items less than last date, or:
                    // equal to last date and less than last ID
                    $builder->any(
                        $builder
                            ->emptyClone()
                            ->lessThan('created_at', $decoded['created_at'])
                            ->all(
                                $builder
                                    ->emptyClone()
                                    ->equals('created_at', $decoded['created_at'])
                                    ->lessThan('id', $decoded['id'])
                            )
                    );
                }
            }
        }
        return $builder;
    }


    /**
     * Create a predicate builder for retrieving the learning moments.
     *
     * @return Builder
     */
    private function buildPredicateBuilder(): Builder
    {
        return $this->mapper->getPredicateBuilder()
                            ->equals('user_id', $this->user->getId());
    }
}
