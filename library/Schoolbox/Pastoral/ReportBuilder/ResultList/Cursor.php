<?php

namespace Schoolbox\Pastoral\ReportBuilder\ResultList;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\ReportBuilder\Base;
use Schoolbox\Pastoral\ReportBuilder\Factory;
use Schoolbox\Pastoral\ReportBuilder\Helper;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByType;
use Schoolbox\Pastoral\ReportBuilder\TabType\TypeByMonth;
use Schoolbox\Pastoral\ReportBuilder\TabType\TypeByWeek;
use ValueError;

class Cursor implements ICursor
{
    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 20 results is a sensible maximum.
     */
    public const LIMIT_MAX = 20;

    /**
     * Minimum number of results returnable and should really be more than 0
     */
    public const LIMIT_MIN = 1;

    /**
     * A sensible default number of results to return from the cursor at once:
     * IMO the maximum is a sensible default.
     */
    public const LIMIT_DEFAULT = self::LIMIT_MAX;
    public const BAD_REQUEST_ERROR_CODE = 400;

    public function __construct(
        private Helper $helper,
        private PastoralMapper $pastoralMapper,
        private string $tabType,
        private string $resultType,
        private ?string $columnKey,
        private ?array $rowKey = null,
        private ?int $limit = self::LIMIT_DEFAULT,
        private ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();

        if (null !== $this->limit) {
            /* Set a maximum number of results should be returned otherwise reject
             */
            if ($this->limit > self::LIMIT_MAX) {
                throw new ValueError(
                    sprintf('Limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit),
                    self::BAD_REQUEST_ERROR_CODE
                );
            }

            /* Set a minimum number of results should be returned otherwise reject
             */
            if ($this->limit < self::LIMIT_MIN) {
                throw new ValueError(
                    sprintf('Limit must be at least %d: %d provided', self::LIMIT_MIN, $this->limit),
                    self::BAD_REQUEST_ERROR_CODE
                );
            }
        }
    }

    /**
     * Get the SQL snippet to filter out null points if the result type is points
     *
     * @return string
     */
    private function getWhereResultType(): string
    {
        return ($this->resultType === Base::RESULT_TYPE_POINTS) ?
            "AND (pastoral.points IS NOT NULL)" :
            '';
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
                'occurred' => $last->getOccurredOn()?->getDatabase() ?? Base::MIN_DATE_VALUE,
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

    public function filterByCell(): array
    {
        $where = $param = [];
        if (null !== $this->rowKey) {
            [$rowWhere, $rowParam] = $this->getRowKeyByTabType();
            $where = array_merge($where, $rowWhere);
            $param = array_merge($param, $rowParam);
        }

        if (null !== $this->columnKey && 'total' !== $this->columnKey) {
            [$rowWhere, $rowParam] = $this->getColumnKeyByTabType();
            $where = array_merge($where, $rowWhere);
            $param = array_merge($param, $rowParam);
        }

        return [
            'where' => implode(' AND ', $where),
            'param' => $param,
        ];
    }

    /**
     * @inheritdoc
     *
     * @return ?int
     */
    public function retrieveResultCount(): ?int
    {
        $db = $this->pastoralMapper->getDatabase();
        $where = implode(' AND ', $this->helper->getFilters());

        $filter = $this->filterByCell();
        $whereFilter = $filter['where'];

        $hideNullPoints = $this->getWhereResultType();

        $statement = $db->executeQuery(
            "
            SELECT COUNT(DISTINCT pastoral.id) as count
            {$this->helper->getFromString()}
            WHERE {$where}
            {$hideNullPoints}
            AND {$whereFilter}
        ",
            array_merge(
                $this->helper->getParams(),
                $filter['param']
            )
        );

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * @inheritdoc
     *
     * @param string|null $encodedCursor
     * @return Collection|PastoralModel[]
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

            // Create lexicographic comparison for multiple sort fields
            $cursorConditions = $this->helper->buildCursorConditions($cursor, Base::MIN_SORT_BY_VALUE, Base::MIN_DATE_VALUE);

            if (!empty($cursorConditions)) {
                $where .= " AND (" . implode(' OR ', $cursorConditions['conditions']) . ")";
                $param = $cursorConditions['params'];
            }
        }

        $filter = $this->filterByCell();
        $whereFilter = $filter['where'];

        $db = $this->pastoralMapper->getDatabase();
        $limit = "";
        if (null !== $this->limit) {
            $limit = " LIMIT :limit ";
            $param['limit'] = $this->limit;
        }

        $hideNullPoints = $this->getWhereResultType();

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
            {$hideNullPoints}
            AND {$whereFilter}
            ORDER BY {$orderSql}
            {$limit}
        ",
            array_merge(
                $this->helper->getParams(),
                $filter['param'],
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
     * @return array
     */
    private function getRowKeyByTabType(): array
    {
        $query = $param = [];
        switch ($this->tabType) {
            case Factory::STUDENT_BY_TYPE:
            case Factory::STUDENT_BY_MONTH:
            case Factory::STUDENT_BY_WEEK:
                $query[] = "pastoral.student_id = :student_id";
                if (!isset($this->rowKey['student_id'])) {
                    throw new ValueError('Student ID is required', self::BAD_REQUEST_ERROR_CODE);
                }
                $param['student_id'] = $this->rowKey['student_id'];
                break;
            case Factory::TYPE_BY_WEEK:
            case Factory::TYPE_BY_MONTH:
                $query[] = "pastoral.type_id = :type_id AND COALESCE(pastoral.subtype_id, '') = COALESCE(:subtype_id, '')";
                if (!isset($this->rowKey['type']['type_id'])) {
                    throw new ValueError('Type ID is required');
                }
                $param['type_id'] = $this->rowKey['type']['type_id'];
                $param['subtype_id'] = $this->rowKey['type']['subtype_id'] ?? null;
                break;
            default:
                throw new ValueError(sprintf('Unknown tab type: %s', $this->tabType), self::BAD_REQUEST_ERROR_CODE);
        }

        return [$query, $param];
    }

    /**
     * @return array
     */
    private function getColumnKeyByTabType(): array
    {
        $query = $param = [];
        switch ($this->tabType) {
            case Factory::STUDENT_BY_TYPE:
                if (!preg_match(Base::BY_TYPE_COLUMN_KEY_REGEX, $this->columnKey, $matches)) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $query[] = "pastoral.{$matches['type']}_id = :type_id";
                $param['type_id'] = $matches['id'];
                break;
            case Factory::TYPE_BY_MONTH:
            case Factory::STUDENT_BY_MONTH:
                if (!preg_match(Base::BY_MONTH_COLUMN_KEY_REGEX, $this->columnKey, $matches)) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $monthFormat = TypeByMonth::COLUMN_QUERY_MONTH_FORMAT;
                $query[] = "DATE_FORMAT(COALESCE(pastoral.occurred_on, pastoral.created_at), '{$monthFormat}') = :monthKey";
                $param['monthKey'] = $this->columnKey;
                break;
            case Factory::TYPE_BY_WEEK:
            case Factory::STUDENT_BY_WEEK:
                if (!preg_match(Base::BY_WEEK_COLUMN_KEY_REGEX, $this->columnKey, $matches)) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $weeks = $this->helper->getWeeksInRange(
                    $this->helper->getStartDate(),
                    $this->helper->getEndDate(),
                    TypeByWeek::COLUMN_QUERY_KEY,
                );
                if (!$weeks->offsetExists($matches['index'])) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $query[] = "COALESCE(pastoral.occurred_on, pastoral.created_at) BETWEEN :start AND :end";
                $week = array_reverse($weeks->getAll())[$matches['index']];
                $param['start'] = $week['start']->toIso8601String();
                $param['end'] = $week['end']->toIso8601String();
                break;
            default:
                throw new ValueError(sprintf('Unknown tab type: %s', $this->tabType), self::BAD_REQUEST_ERROR_CODE);
        }
        return [$query, $param];
    }
}
