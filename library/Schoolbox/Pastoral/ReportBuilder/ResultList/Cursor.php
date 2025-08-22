<?php

namespace Schoolbox\Pastoral\ReportBuilder\ResultList;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\ReportBuilder\Factory;
use Schoolbox\Pastoral\ReportBuilder\Helper;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByType;
use Schoolbox\Pastoral\ReportBuilder\TabType\TypeByMonth;
use Schoolbox\Pastoral\ReportBuilder\TabType\TypeByWeek;
use ValueError;

class Cursor implements ICursor
{
    private string $inTypeSubType;
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
        private ?string $columnKey,
        private ?array $rowKey = null,
        private ?int $limit = self::LIMIT_DEFAULT,
        private ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();

        /* Set a maximum number of results should be returned otherwise reject
         */
        if (null !== $this->limit) {
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
            $whereInTypeSubTypeQuery[] = "p.type_id IN ({$whereTypeIds})";
        }
        if ($this->helper->getSubTypes()->count() > 0) {
            $whereSubTypeIds = implode(',', $this->helper->getSubTypes()->getPropertyValues('id'));
            $whereInTypeSubTypeQuery[] = "p.subtype_id IN ({$whereSubTypeIds})";
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


        $statement = $db->executeQuery(
            "
            SELECT COUNT(*) as count
            {$this->helper->getFromString()}
            WHERE {$where}
            AND {$whereFilter}
            AND ({$this->inTypeSubType})
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
        $db = $this->pastoralMapper->getDatabase();

        $where = implode(' AND ', $this->helper->getFilters());
        $param = [];
        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
            $where .= " AND p.id > :cursor_id";
            $param['cursor_id'] = $cursor['id'];
        }

        $filter = $this->filterByCell();
        $whereFilter = $filter['where'];

        $limit = (null === $this->limit) ? "" : " LIMIT {$this->limit}";


        $statement = $db->executeQuery(
            "
            SELECT p.id
            {$this->helper->getFromString()}
            WHERE {$where}
            AND {$whereFilter}
            AND ({$this->inTypeSubType})
            ORDER BY p.id ASC
            {$limit}
        ",
            array_merge(
                $this->helper->getParams(),
                $filter['param'],
                $param,
            )
        );

        $pastoralIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

        $results = $this->pastoralMapper->findByColumn('id', $pastoralIds)->reindex();

        return $results;
    }


    /**
     * @return array
     */
    private function getRowKeyByTabType(): array
    {
        $query = $param = [];
        switch ($this->tabType) {
            case Factory::STUDENT_BY_TYPE:
                $query[] = "p.student_id = :student_id";
                if (!isset($this->rowKey['student_id'])) {
                    throw new ValueError('Student ID is required', self::BAD_REQUEST_ERROR_CODE);
                }
                $param['student_id'] = $this->rowKey['student_id'];
                break;
            case Factory::TYPE_BY_WEEK:
            case Factory::TYPE_BY_MONTH:
                $query[] = "p.type_id = :type_id AND COALESCE(p.subtype_id, '') = COALESCE(:subtype_id, '')";
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
                if (!preg_match(StudentByType::COLUMN_KEY_REGEX, $this->columnKey, $matches)) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $query[] = "p.{$matches['type']}_id = :type_id";
                $param['type_id'] = $matches['id'];
                break;
            case Factory::TYPE_BY_MONTH:
                if (!preg_match(TypeByMonth::COLUMN_KEY_REGEX, $this->columnKey, $matches)) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $monthFormat = TypeByMonth::COLUMN_QUERY_MONTH_FORMAT;
                $query[] = "DATE_FORMAT(COALESCE(p.occurred_on, p.created_at), '{$monthFormat}') = :monthKey";
                $param['monthKey'] = $this->columnKey;
                break;
            case Factory::TYPE_BY_WEEK:
                if (!preg_match(TypeByWeek::COLUMN_KEY_REGEX, $this->columnKey, $matches)) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $weeks = $this->helper->getWeeksInRange(
                    $this->helper->getStartDate(),
                    $this->helper->getEndDate(),
                    TypeByWeek::COLUMN_QUERY_KEY,
                    $this->helper->getConfigLoader()->get('date_short'),
                    TypeByWeek::MAX_COLUMN_COUNT,
                );
                if (!$weeks->offsetExists($matches['index'])) {
                    throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey), self::BAD_REQUEST_ERROR_CODE);
                }
                $query[] = "COALESCE(p.occurred_on, p.created_at) BETWEEN :start AND :end";
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
