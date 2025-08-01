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

    public function __construct(
        private Helper $helper,
        private PastoralMapper $pastoralMapper,
        private string $tabType,
        private ?string $columnKey,
        private ?string $rowKey = null,
        private ?int $limit = self::LIMIT_DEFAULT,
        private ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();

        /* Set a maximum number of results should be returned otherwise reject
 */
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(sprintf('Limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit));
        }

        /* Set a minimum number of results should be returned otherwise reject
         */
        if ($this->limit < self::LIMIT_MIN) {
            throw new ValueError(sprintf('Limit must be at least %d: %d provided', self::LIMIT_MIN, $this->limit));
        }
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
            $where[] = "p.student_id = :student_id";
            $param['student_id'] = $this->rowKey;
        }

        if (null !== $this->columnKey && 'total' !== $this->columnKey) {
            $match = $this->getColumnKeyByTabType();
            $where[] = "p.{$match['type']}_id = :column_id";
            $param['column_id'] = $match['id'];
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

        $statement = $db->executeQuery(
            "
            SELECT p.id
            {$this->helper->getFromString()}
            WHERE {$where}
            AND {$whereFilter}
            ORDER BY p.id ASC
            LIMIT {$this->limit}
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
    private function getColumnKeyByTabType(): array
    {
        $matches = [];
        switch ($this->tabType) {
            case Factory::STUDENT_BY_TYPE:
                preg_match(StudentByType::COLUMN_KEY_REGEX, $this->columnKey, $matches);
                break;
            default:
                throw new ValueError(sprintf('Unknown tab type: %s', $this->tabType));
        }

        if (empty($matches)) {
            throw new ValueError(sprintf('Unknown column key: %s', $this->columnKey));
        }

        return $matches;
    }
}
