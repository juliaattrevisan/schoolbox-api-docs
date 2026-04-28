<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Nationality;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lu_nationality');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('name', self::TYPE_STRING);
    }

    /**
     * Find all nationalities matching a certain name
     *
     * @param string $name
     * @return GenericCollection
     * @throws Exception
     */
    public function findNationalities($name = ''): GenericCollection
    {
        $allNationalities = $this->createCollection();

        /* if $name is empty, the LIKE returns everything anyway
         */
        $query = "
            SELECT
                {$this->getColumnString('n')}
            FROM
                `{$this->getTable()}` n
            WHERE n.name LIKE :name
            ORDER BY n.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(
            [
                'name' => "%{$name}%",
            ]
        );

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $allNationalities->add($this->hydrate($row, 'n'));
        }

        return $allNationalities;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            'name' => self::SORT_ORDER_ASC,
            'code' => self::SORT_ORDER_ASC
        ];
    }
}
