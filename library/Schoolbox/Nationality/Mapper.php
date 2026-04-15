<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Nationality;

use Schoolbox\Campus;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

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
        $this->uses('name', self::TYPE_STRING);
    }

    /**
     * Find all campuses matching a certain name
     *
     * @param string $name
     * @return GenericCollection|Campus[]
     * @throws Mapper\Exception
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
}
