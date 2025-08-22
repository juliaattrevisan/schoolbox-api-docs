<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\ReportBuilder\Base;
use Schoolbox\Pastoral\ReportBuilder\Helper;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray as PastoralTranslator;

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
        $db = $this->pastoralMapper->getDatabase();

        $where = implode(' AND ', $this->helper->getFilters());
        $param = [];
        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
            $where .= " AND p.id > :cursor_id";
            $param['cursor_id'] = $cursor['id'];
        }

        $statement = $db->executeQuery(
            "
            SELECT p.id
            {$this->helper->getFromString()}
            WHERE {$where}
            AND ({$this->inTypeSubType})
            ORDER BY p.id ASC
            {$this->limit}
        ",
            array_merge(
                $this->helper->getParams(),
                $param,
            )
        );

        $pastoralIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

        $order = [];
        foreach ($this->helper->getReportOrder() as $reportOrder) {
            $order[$reportOrder['column']] = $reportOrder['direction'] ?? 'ASC';
        }

        $records = $this->pastoralMapper
            ->findByColumn(
                'id',
                $pastoralIds,
                [
                    'order' => $order
                ]
            )->reindex();


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
