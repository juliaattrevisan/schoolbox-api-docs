<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Closure;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\DataSource;
use Schoolbox\Pastoral\Mapper;
use Schoolbox\Pastoral\Model;
use Schoolbox\User;
use ValueError;

/**
 * Cursor for pastoral records in the class report.
 */
class Cursor implements ICursor
{
    /**
     * Codec, for translating the cursor.
     */
    private ICodec $codec;

    public const SORT_OPTION_CREATED = 'created';
    public const SORT_OPTION_UPDATED = 'updated';
    public const SORT_OPTION_SEVERITY = 'severity';

    public const LIMIT_MIN     = 1;
    public const LIMIT_DEFAULT = 7;
    public const LIMIT_MAX     = 12;

    /**
     * Constructor
     *
     * @param DataSource $dataSource  Pastoral data source
     * @param User       $currentUser The user for whom results should be censored
     * @param User       $student     The student to fetch results for
     * @param array      $pastoralIds A list of IDs already determined to match a filter
     * @param string     $sortOption  A field to order results by
     * @param int        $limit       The max number of results to fetch
     */
    public function __construct(
        private DataSource $dataSource,
        private User $currentUser,
        private User $student,
        private array $pastoralIds,
        private string $sortOption = self::SORT_OPTION_CREATED,
        private int $limit = self::LIMIT_DEFAULT,
    ) {
        if ($this->limit < self::LIMIT_MIN || $this->limit > self::LIMIT_MAX) {
            throw new ValueError(
                sprintf(
                    '$limit parameter must be within [%d, %d]: received %d',
                    self::LIMIT_MIN,
                    self::LIMIT_MAX,
                    $this->limit,
                )
            );
        }
        $this->codec = new Base64();
    }

    /**
     * @inheritdoc
     */
    public function next(Collection $collection): ?string
    {
        $nextCursor  = null;
        $lastRecord  = $collection->last();

        if ($lastRecord instanceof Model) {
            // Simplified cursor, based only on ID: see "Simplified filtering"
            // in `retrieveResults` for discussion
            $nextCursor = $this->codec->encode([
                'severity' => $lastRecord->getSeverity()->getSequence(),
                'created'  => $lastRecord->getCreatedAt()->getUnixTimeStamp(),
                'updated'  => $lastRecord->getUpdatedAt()->getUnixTimeStamp(),
                'id'       => $lastRecord->getId(),
            ]);
        }
        return $nextCursor;
    }

    /**
     * @inheritdoc
     */
    public function current(Collection $collection): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function retrieveResultCount(): ?int
    {
        if (count($this->pastoralIds) === 0) {
            return 0;
        }
        return $this->dataSource->countByColumnList(
            $this->currentUser,
            [
                'id'         => $this->pastoralIds,
                'student_id' => $this->student->getId(),
            ],
        );
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $pb      = $this->buildPredicateBuilder($encodedCursor);
        $results = $this->dataSource->findRecordsByPredicates(
            $this->currentUser,
            $pb,
            [
                'order' => $this->buildOrderPart(),
                'limit' => $this->limit,
            ],
        );

        // TODO limit the results returned in query, instead of post-query:
        //      need to update findByColumnList to do so
        $results = $this->dataSource->findRecords(
            $this->currentUser,
            $this->student->getId(),
            null,
            null,
            0,
            0,
            null,
        );
        $results = $results->filter(fn (Model $model) => in_array($model->getId(), $this->pastoralIds));
        $results = $results->sortByCallback($this->buildSortCallback());
        if (count($results) === 0) {
            return $results;
        }

        // Simplified filtering:
        // Find the ID in the encoded cursor, and return every record after that
        // in already-sorted `$results`.
        // The alternative is to be aware of every ordering option in each sort
        // option, and filter conditionally on that.
        // e.g. the severity filter would need to exclude any result with:
        // * a severity higher than the cursor
        // * a severity equal to the cursor, and a created higher than the
        //   cursor
        // * a severity and created equal to the cursor, and an ID higher than
        //   the cursor
        $offset = 0;
        if (isset($encodedCursor)) {
            $decodedCursor = $this->codec->decode($encodedCursor);
            $id            = $decodedCursor['id'] ?? null;
            if ($id) {
                $resultIds    = $results->getPropertyValues('id');
                $cursorOffset = array_search($id, $resultIds, true);
                // Not found: return an empty result
                if ($cursorOffset === false) {
                    return $results->emptyClone();
                } else {
                    $offset = $cursorOffset + 1;
                }
            }
        }

        return $results->slice($offset, $this->limit);
    }

    /**
     * Build a sort callback for this type of record.
     *
     * Ordering of results:
     * * severity: severity.sequence DESC, then created DESC, then id DESC
     * * created:  created DESC, then id DESC
     * * updated:  updated DESC, then created DESC, then id DESC
     *
     * @return Closure
     */
    private function buildSortCallback(): Closure
    {
        $callback = match ($this->sortOption) {
            self::SORT_OPTION_SEVERITY => function (Model $a, Model $b) {
                return $this->sortSeverity($a, $b) ?: $this->sortCreated($a, $b) ?: $this->sortId($a, $b);
            },
            self::SORT_OPTION_UPDATED => function (Model $a, Model $b) {
                return $this->sortUpdated($a, $b) ?: $this->sortCreated($a, $b) ?: $this->sortId($a, $b);
            },
            default => function (Model $a, Model $b) {
                return $this->sortCreated($a, $b) ?: $this->sortId($a, $b);
            },
        };
        return Closure::fromCallable($callback);
    }

    /**
     * Sort pastoral records by severity (descending).
     *
     * @param Model $a
     * @param Model $b
     * @return int
     */
    private function sortSeverity(Model $a, Model $b): int
    {
        if (!($a->getSeverity() && $b->getSeverity())) {
            return 0;
        }
        return $b->getSeverity()->getSequence() <=> $a->getSeverity()->getSequence();
    }

    /**
     * Sort pastoral records by created (descending)
     *
     * @param Model $a
     * @param Model $b
     * @return int
     */
    private function sortCreated(Model $a, Model $b): int
    {
        return $b->getCreatedAt()->getUnixTimeStamp() <=> $a->getCreatedAt()->getUnixTimeStamp();
    }

    /**
     * Sort pastoral records by updated (descending)
     *
     * @param Model $a
     * @param Model $b
     * @return int
     */
    private function sortUpdated(Model $a, Model $b): int
    {
        return $b->getUpdatedAtDisplay()->getUnixTimeStamp() <=> $a->getUpdatedAtDisplay()->getUnixTimeStamp();
    }

    /**
     * Sort pastoral records by ID (descending)
     *
     * @param Model $a
     * @param Model $b
     * @return int
     */
    private function sortId(Model $a, Model $b): int
    {
        return $b->getId() <=> $a->getId();
    }


    // methods for rewritten query


    private function buildOrderPart(): array
    {
        return match ($this->sortOption) {
            self::SORT_OPTION_SEVERITY => [
                'sev.sequence' => Mapper::SORT_ORDER_DESC,
                'created_at'   => Mapper::SORT_ORDER_DESC,
                'id'           => Mapper::SORT_ORDER_DESC,
            ],
            self::SORT_OPTION_UPDATED => [
                'updated_at' => Mapper::SORT_ORDER_DESC,
                'created_at' => Mapper::SORT_ORDER_DESC,
                'id'         => Mapper::SORT_ORDER_DESC,
            ],
            default => [
                'created_at' => Mapper::SORT_ORDER_DESC,
                'id'         => Mapper::SORT_ORDER_DESC,
            ],
        };
    }

    private function buildBasePredicateBuilder(): Builder
    {
        return Mapper::getInstance()->getPredicateBuilder();
    }


    private function buildPredicateBuilder(?string $encodedCursor): Builder
    {
        $pb = $this->buildBasePredicateBuilder()
                   ->equals('student_id', $this->student->getId())
                   ->in('id', $this->pastoralIds);
        if (!isset($encodedCursor)) {
            return $pb;
        }

        $cursor = $this->codec->decode($encodedCursor);
        if (!isset($cursor['severity'], $cursor['created'], $cursor['updated'], $cursor['id'])) {
            return $pb;
        }

        if ($this->sortOption === self::SORT_OPTION_SEVERITY) {
            // Severity: severity, then created, then ID
            $pb = $pb->any(
                $this->buildBasePredicateBuilder()
                     ->lessThan('sev.sequence', $cursor['severity'])
                     ->all(
                         $this->buildBasePredicateBuilder()
                              ->equals('sev.sequence', $cursor['severity'])
                              ->lessThan('created_at', $cursor['created'])
                     )
                     ->all(
                         $this->buildBasePredicateBuilder()
                              ->equals('sev.sequence', $cursor['severity'])
                              ->equals('created_at', $cursor['created'])
                              ->lessThan('id', $cursor['id'])
                     )
            );
        } elseif ($this->sortOption === self::SORT_OPTION_UPDATED) {
            // Updated: updated, then created, then ID
            $pb = $pb->any(
                $this->buildBasePredicateBuilder()
                     ->lessThan('updated_at', $cursor['updated'])
                     ->all(
                         $this->buildBasePredicateBuilder()
                              ->equals('updated_at', $cursor['updated'])
                              ->lessThan('created_at', $cursor['created'])
                     )
                     ->all(
                         $this->buildBasePredicateBuilder()
                              ->equals('updated_at', $cursor['severity'])
                              ->equals('created_at', $cursor['created'])
                              ->lessThan('id', $cursor['id'])
                     )
            );
        } else {
            // Created: created, then ID
            $pb = $pb->any(
                $this->buildBasePredicateBuilder()
                     ->lessThan('created_at', $cursor['created'])
                     ->all(
                         $this->buildBasePredicateBuilder()
                              ->equals('created_at', $cursor['created'])
                              ->lessThan('id', $cursor['id'])
                     )
            );
        }

        return $pb;
    }
}
