<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Language;

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
        $this->hasTable('lu_language');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
    }

    /**
     * Find all campuses matching a certain name
     *
     * @param string $name
     * @return GenericCollection|Model[]
     * @throws Exception
     */
    public function findLanguages($name = ''): GenericCollection
    {
        $allLanguages = $this->createCollection();

        /* if $name is empty, the LIKE returns everything anyway
         */
        $query = "
            SELECT
                {$this->getColumnString('l')}
            FROM
                `{$this->getTable()}` l
            WHERE l.name LIKE :name
            ORDER BY l.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(
            [
                'name' => "%{$name}%",
            ]
        );

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $allLanguages->add($this->hydrate($row, 'l'));
        }

        return $allLanguages;
    }
}
