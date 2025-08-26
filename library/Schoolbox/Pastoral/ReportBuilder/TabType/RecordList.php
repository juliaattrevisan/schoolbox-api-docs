<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Report\Model as Report;
use Schoolbox\Pastoral\ReportBuilder\Base;
use Schoolbox\Pastoral\ReportBuilder\Helper;
use Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray as PastoralTranslator;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;

class RecordList extends Base implements TabTypeInterface, ICursor
{
    private string $inTypeSubType;
    public function __construct(
        protected Helper $helper,
        protected ModelToArray $userTranslator,
        protected UserMapper $userMapper,
        protected PastoralMapper $pastoralMapper,
        protected PastoralTranslator $pastoralTranslator,
        protected string $resultType,
        protected int $limit = self::LIMIT_DEFAULT,
    ) {
        parent::__construct(
            $this->helper,
            $this->userTranslator,
            $this->userMapper,
            $this->pastoralMapper,
            $this->resultType,
            null,
            null,
            $this->limit
        );

        $this->init();
    }

    /**
     * Initialise the cursor with some common values
     *
     * @return void
     */
    private function init(): void
    {
        $whereInTypeSubTypeQuery = [];
        if ($this->helper->getType()->count() > 0) {
            $whereTypeIds = implode(',', $this->helper->getType()->getPropertyValues('id'));
            $whereInTypeSubTypeQuery[] = "pastoral.type_id IN ({$whereTypeIds})";
        }
        if ($this->helper->getSubTypes()->count() > 0) {
            $whereSubTypeIds = implode(',', $this->helper->getSubTypes()->getPropertyValues('id'));
            $whereInTypeSubTypeQuery[] = "pastoral.subtype_id IN ({$whereSubTypeIds})";
        }

        $this->inTypeSubType = implode(' OR ', array_filter($whereInTypeSubTypeQuery));
    }

    /**
     * @inheritdoc
     *
     * @param Collection|PastoralModel[] $collection
     * @return string|null
     */
    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        /* If result size is smaller than limit, assumed that it is at the end of pointer and terminate early
         */
        if ($collection->count() < $this->limit) {
            return null;
        }

        /** @var PastoralModel $last */
        $last = $collection->last();
        return $this->codec->encode(
            [
                'id' => $last->getId(),
                'created' => $last->getOccurredOn()?->toIso8601String() ?? $last->getCreatedAt()->toIso8601String(),
                'student' => [
                    'chosen' =>
                        ((string)$last->getStudent()->getPreferredName()) !== '' ?
                            $last->getStudent()->getPreferredName() :
                            $last->getStudent()->getFirstName(),
                    'last' => $last->getStudent()->getLastName(),
                ],
                'type' => $last->getType()->getName(),
                'subtype' => $last->getSubType()->getName(),
                'author' => [
                    'chosen' =>
                        ((string)$last->getOwner()->getPreferredName()) !== '' ?
                            $last->getOwner()->getPreferredName() :
                            $last->getOwner()->getFirstName(),
                    'last' => $last->getOwner()->getLastName(),
                ],
                'points' => $last->getPoints(),
                'yearLevel' => $last->getStudent()->getYear()->getSequence(),
                'house' => $last->getStudent()->getSchoolHouse()->getHouse(),
                'campus' => $last->getStudent()->getCampusCollection()->first()->getName(),
                'severity' => $last->getSeverity()->getSequence(),
            ]
        );
    }

    /**
     * @inheritdoc
     *
     * @param Collection|PastoralModel[] $collection
     * @return string|null
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
        $db = $this->pastoralMapper->getDatabase();
        $where = implode(' AND ', $this->helper->getFilters());

        $statement = $db->executeQuery(
            "
            SELECT COUNT(*) as count
            {$this->helper->getFromString()}
            WHERE {$where}
            AND ({$this->inTypeSubType})
        ",
            $this->helper->getParams(),
        );

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $where = implode(' AND ', $this->helper->getFilters());
        $param = [];
        $order = [];

        foreach ($this->helper->getReportOrder() as $reportOrder) {
            $order[$reportOrder['column']] = $reportOrder['direction'] ?? 'ASC';
        }

        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
            $where .= " AND ({$this->inTypeSubType})";

            // Create lexicographic comparison for multiple sort fields
            $cursorConditions = $this->helper->buildCursorConditions($cursor, self::MIN_SORT_BY_VALUE);

            if (!empty($cursorConditions)) {
                $where .= " AND (" . implode(' OR ', $cursorConditions['conditions']) . ")";
                $param = $cursorConditions['params'];
            }
        }

        $pb = $this->pastoralMapper->getPredicateBuilder();

        $pb->rawWhere(
            $where,
            array_merge(
                $this->helper->getParams(),
                $param,
            )
        );

        $records = $this->pastoralMapper
            ->findByPredicates($pb, [
                'order' => $order,
            ])
            ->limit($this->limit)
            ->reindex();

        return $records;
    }

    /**
     * Processing the payload to format it for presenting in a table
     *
     * @param Collection  $records
     * @param string|null $encodedCursor
     * @return array[]
     */
    public function process(Collection $records, ?string $encodedCursor = null)
    {
        $records = $this->retrieveResults($encodedCursor);

        return [
            'data' => [
                'results' => $records->map($this->pastoralTranslator),
            ],
            'metadata' => [
                'count'  => $this->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $this->current($records),
                    'next'    => $this->next($records) ?? null
                ]
            ]
        ];
    }
}
