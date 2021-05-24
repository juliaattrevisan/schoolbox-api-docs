<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Log;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_import_log');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        // Intentionally not hydrating these as its only for logging

        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('import_id', self::TYPE_INTEGER);
        $this->uses('user_id', self::TYPE_INTEGER);
        $this->uses('student_name', self::TYPE_STRING);
        $this->uses('creator_name', self::TYPE_STRING);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('result', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }

    /**
     * Retrieves a list of unique import IDs from the log table along with the date the import started
     *
     * @return Collection
     */
    public function findImportIds()
    {
        $userMapper = UserMapper::getInstance();

        $query = "
            SELECT DISTINCT   l.import_id,
                              (
                                  SELECT MIN(created_at) 
                                  FROM pastoral_import_log 
                                  WHERE import_id = l.import_id) AS created_at,
                              l.user_id,
                              (
                                  SELECT result FROM pastoral_import_log WHERE id = (
                                    SELECT id
                                    FROM pastoral_import_log
                                    WHERE import_id = l.import_id
                                    ORDER BY id DESC
                                    LIMIT 1
                                  )
                              ) AS result
            FROM              pastoral_import_log l
            WHERE             created_at IS NOT NULL AND creator_name IS NOT NULL
            GROUP BY          created_at
            ORDER BY          import_id DESC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        /** @var Collection $result */
        $result = new Collection();

        foreach ($statement as $row) {
            $result->add([
                'importId'  => $row['import_id'],
                'createdAt' => Alaress_Date_Format::newDatabaseDate($row['created_at']),
                'creator'   => $userMapper->findByIdWithFallback($row['user_id']),
                'result'    => $row['result'],
            ]);
        }

        return $result;
    }
}
