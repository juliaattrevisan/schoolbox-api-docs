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
    public const LIMIT_DEFAULT = 50; // increase from 20 so records linked to recent notifications are more likely to be included in the initial load
    public const LIMIT_MAX = 50;
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
                'created' => $last->getCreatedAt()->getDatabase(),
                /* Should default to the earliest date if null
                 */
                'occurred' => $last->getOccurredOn()?->getDatabase() ?? self::MIN_DATE_VALUE,
                'student' => [
                    'chosen' =>
                        ((string)$last->getStudent()->getPreferredName()) !== '' ?
                            $last->getStudent()->getPreferredName() :
                            $last->getStudent()->getFirstName(),
                    'last' => $last->getStudent()->getLastName(),
                ],
                'type' => $last->getType()->getName(),
                'subtype' => $last->getSubType()?->getName(),
                'author' => [
                    'chosen' =>
                        ((string)$last->getOwner()->getPreferredName()) !== '' ?
                            $last->getOwner()->getPreferredName() :
                            $last->getOwner()->getFirstName(),
                    'last' => $last->getOwner()->getLastName(),
                ],
                'points' => $last->getPoints(),
                'yearLevel' => $last->getStudent()?->getYear()?->getSequence(),
                'house' => $last->getStudent()?->getSchoolHouse()?->getId(),
                'campus' => $last->getStudent()?->getCampusCollection()?->first()?->getName(),
                'severity' => $last->getSeverity()?->getSequence(),
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
            SELECT COUNT(DISTINCT pastoral.id) as count
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
        $orderSql = '';

        $reportOrder = $this->helper->getReportOrder();
        for ($i = 0; $i < count($reportOrder); $i++) {
            $orderBy = $reportOrder[$i];
            $order[$orderBy['column']] = $orderBy['direction'] ?? 'ASC';
            $orderSql .= "{$orderBy['column']} " . ($orderBy['direction'] ?? 'ASC');
            if ($i < count($reportOrder) - 1) {
                $orderSql .= ', ';
            }
        }

        if ('' !== (string)$encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
            $where .= " AND ({$this->inTypeSubType})";

            // Create lexicographic comparison for multiple sort fields
            $cursorConditions = $this->helper->buildCursorConditions($cursor, self::MIN_SORT_BY_VALUE, self::MIN_DATE_VALUE);

            if (!empty($cursorConditions)) {
                $where .= " AND (" . implode(' OR ', $cursorConditions['conditions']) . ")";
                $param = $cursorConditions['params'];
            }
        }

        $db = $this->pastoralMapper->getDatabase();

        $statement = $db->executeQuery(
            "
            SELECT DISTINCT pastoral.id
            {$this->helper->getFromString()}
            LEFT JOIN lu_year_level AS sy ON s.year_id = sy.id
            INNER JOIN luCampus AS sc ON sc.id = v_sc.campus
            INNER JOIN lu_pastoral_type AS t ON pastoral.type_id = t.id
            LEFT JOIN lu_pastoral_subtype AS c ON pastoral.subtype_id = c.id
            INNER JOIN user AS o on pastoral.owner_id = o.id
            WHERE {$where}
            AND ({$this->inTypeSubType})
            ORDER BY {$orderSql}
            LIMIT {$this->limit}
        ",
            array_merge(
                $this->helper->getParams(),
                $param,
            )
        );

        $pastoralIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

        $records = $this->helper->filterRecordsForUser(
            $this->pastoralMapper
                ->findByColumn(
                    'id',
                    $pastoralIds,
                    ['order' => $order]
                )
                ->reindex()
        );

        return $records;
    }

    /**
     * Processing the payload to format it for presenting in a table
     *
     * @param Collection  $records
     * @param string|null $encodedCursor
     * @return array[]
     */
    public function process(Collection $records, ?string $encodedCursor = null): array
    {
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
