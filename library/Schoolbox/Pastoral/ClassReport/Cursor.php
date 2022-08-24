<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\DataSource;
use Schoolbox\Pastoral\Model;
use Schoolbox\User;

/**
 * Cursor for pastoral records in the class report.
 */
class Cursor implements ICursor
{
    private ICodec $codec;

    /**
     * Constructor
     *
     * @param DataSource $dataSource       Pastoral data source
     * @param User       $currentUser      The user for whom results should be censored
     * @param User       $student          The student to fetch results for
     * @param array      $pastoralIds      A list of IDs already determined to match a filter
     * @param string     $orderByField     A field to order results by
     * @param int        $limit            The max number of results to fetch
     */
    public function __construct(
        private DataSource $dataSource,
        private User $currentUser,
        private User $student,
        private array $pastoralIds,
        private string $orderByField,
        private int $limit,
    ) {
        $this->codec = new Base64();
    }

    /**
     * @inheritdoc
     */
    public function next(Collection $collection): ?string
    {
        $nextCursor  = null;
        $lastRecord  = $collection->last();

        // The next cursor points to the last record
        if ($lastRecord instanceof Model) {
            $nextCursor = $this->codec->encode([
                'severity' => $lastRecord->getSeverity()->getSequence(),
                'created'  => $lastRecord->getCreatedAt()->toRfc3339String(),
                'updated'  => $lastRecord->getUpdatedAt()->toRfc3339String(),
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
        return null;
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        // TODO limit the results returned in query, instead of post-query:
        //      need to update findByColumnList to do so
        // TODO incorporate student ID into search
        $results = $this->dataSource->findByColumnList(
            $this->currentUser,
            [
                'id'         => $this->pastoralIds,
                'student_id' => $this->student->getId(),
            ],
        );

        // Ordering of results:
        // * severity: severity.sequence DESC, then created DESC, then id DESC
        // * created:  created DESC, then id DESC
        // * updated:  updated DESC, then created DESC, then id DESC

        // Slice some off the end
        return $results->slice(0, $this->limit);
    }
}
