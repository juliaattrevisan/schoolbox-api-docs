<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Flag;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use DateTimeInterface;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Pastoral\DataSource;
use Schoolbox\Pastoral\Model;
use Schoolbox\Pastoral\Attachment\Model as AttachmentModel;
use Schoolbox\User;
use ValueError;

/**
 * Cursor for pastoral records displayed in the student flag.
 */
class Cursor implements ICursor
{
    public const int LIMIT_MIN     = 1;
    public const int LIMIT_DEFAULT = self::LIMIT_MAX;
    public const int LIMIT_MAX     = 20;

    private ICodec $codec;

    /**
     * Constructor
     *
     * @param DataSource $dataSource   Pastoral data source
     * @param User       $currentUser  The user for whom results should be censored
     * @param User       $student      The student to fetch results for
     * @param int|null   $typeId       Filter records by pastoral type, if provided
     * @param int|null   $subtypeId    Filter records by pastoral subtype, if provided
     * @param bool       $showArchived Whether expired records should be included
     * @param int        $limit        The max number of results to fetch
     */
    public function __construct(
        private DataSource $dataSource,
        private User $currentUser,
        private User $student,
        private ?int $typeId,
        private ?int $subtypeId,
        private bool $showArchived = false,
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
        if ($collection->count() < $this->limit) {
            return null;
        }

        $lastRecord = $collection->last();
        if (!$lastRecord instanceof Model) {
            return null;
        }

        return $this->codec->encode([
            'created' => $lastRecord->getCreatedAt()->toRfc3339String(),
            'id'      => $lastRecord->getId(),
        ]);
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
        return $this->dataSource->countRecords(
            $this->currentUser,
            $this->student,
            $this->typeId,
            $this->subtypeId,
            $this->showArchived,
        );
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $results = $this->dataSource
            ->findRecordsByPredicates(
                $this->currentUser,
                $this->student,
                $this->buildPredicateBuilder($encodedCursor),
                [
                    'order' => [
                        'p.created_at' => Mapper::SORT_ORDER_DESC,
                        'p.id'         => Mapper::SORT_ORDER_DESC,
                    ],
                    'limit' => $this->limit,
                ],
            )
            ->reindex();


        /** @var \Schoolbox\Pastoral\Tag\Mapper $pastoralTagMapper */
        $pastoralTagMapper = $this->dataSource->getPastoralMapper()->getJoinMapper('tags');
        /** @var \Schoolbox\Pastoral\Attachment\Mapper $pastoralAttachmentMapper */
        $pastoralAttachmentMapper = $this->dataSource->getPastoralMapper()->getJoinMapper('attachments');

        /* Fetch attachments for all retrieved pastoral records in a single query, and group by pastoral ID for easy hydration.
         */
        $attachmentPb = $pastoralAttachmentMapper->getPredicateBuilder();
        $attachments = $pastoralAttachmentMapper->findByPredicates(
            $attachmentPb->in('pastoral_id', Collection::arrayColumn($results, 'id'))
        )->groupByCallback(
            function (AttachmentModel $attachment) {
                return $attachment->getPastoral()->getId();
            }
        );

        /* Hydrate tags and attachments for each pastoral record. Attachments are set to an empty collection if no attachments exist for a given record, to avoid null checks in the frontend.
         */
        $results->map(function (Model $record) use ($pastoralTagMapper, $attachments) {
            $record->setTags($pastoralTagMapper->findForRecord($record));
            $record->setAttachments($attachments->getOffset($record->getId()) ?? new GenericCollection(AttachmentModel::class));
            return $record;
        });

        return $results;
    }

    /**
     * Build the predicate builder for this cursor.
     *
     * @param string|null $encodedCursor
     * @return Builder
     */
    private function buildPredicateBuilder(?string $encodedCursor): Builder
    {
        $pb = $this->dataSource->getPredicateBuilder()
            ->equals('p.student_id', $this->student->getId());

        if (null !== $this->typeId) {
            $pb->equals('p.type_id', $this->typeId);
        }

        if (null !== $this->subtypeId) {
            $pb->equals('p.subtype_id', $this->subtypeId);
        }

        if (!$this->showArchived) {
            $pb->any(
                $this->dataSource->getPredicateBuilder()
                    ->any(
                        $this->dataSource->getPredicateBuilder()
                            ->greaterThanOrEqualTo('p.expires_at', Alaress_Date_Format::now()->getDatabaseDate())
                    )
                    ->any(
                        $this->dataSource->getPredicateBuilder()
                            ->isNull('p.expires_at')
                    )
            );
        }

        if (!isset($encodedCursor)) {
            return $pb;
        }

        $cursor = $this->codec->decode($encodedCursor);
        if (!isset($cursor['created'], $cursor['id'])) {
            return $pb;
        }
        $created = (new Alaress_Date_Format($cursor['created'], DateTimeInterface::RFC3339))->getDatabase();

        return $pb->any(
            $this->dataSource->getPredicateBuilder()
                ->lessThan('p.created_at', $created)
                ->all(
                    $this->dataSource->getPredicateBuilder()
                        ->equals('p.created_at', $created)
                        ->lessThan('p.id', $cursor['id'])
                )
        );
    }
}
