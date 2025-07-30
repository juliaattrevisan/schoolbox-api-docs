<?php

namespace Schoolbox\Pastoral\ReportBuilder\ResultList;

use Config\Modules\Pastoral;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\ReportBuilder\Helper;

class Cursor implements ICursor
{
    public function __construct(
        private Helper $helper,
        private PastoralMapper $pastoralMapper,
    ) {
    }

    public function next(Collection $collection): ?string
    {
        return null;
    }

    public function current(Collection $collection): ?string
    {
        return null;
    }

    public function retrieveResultCount(): ?int
    {
        $db = $this->pastoralMapper->getDatabase();
        $where = implode(' AND ', $this->helper->getFilters());

        $statement = $db->executeQuery(
            "
            SELECT COUNT(*) as count
            {$this->helper->getFromString()}
            WHERE {$where}
        ",
            $this->helper->getParams()
        );

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {

        $db = $this->pastoralMapper->getDatabase();

        $where = implode(' AND ', $this->helper->getFilters());

        $statement = $db->executeQuery(
            "
            SELECT p.id
            {$this->helper->getFromString()}
            WHERE {$where}
        ",
            $this->helper->getParams()
        );

        $pastoralIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

        $results = $this->pastoralMapper->findByColumn('id', $pastoralIds);

        return $results;
    }
}
