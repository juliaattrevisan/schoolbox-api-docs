<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Admin\Import\Pastoral;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_import_log');
        $this->hasPrototype(new Model);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('import_id', self::TYPE_INTEGER);
        $this->uses('user_id', self::TYPE_INTEGER);
        $this->uses('student_name', self::TYPE_STRING);
        $this->uses('creator_name', self::TYPE_STRING);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('result', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
    }

    public function findImportIds()
    {
        $query = "
            SELECT DISTINCT import_id, MIN(created_at) as created_at
            FROM {$this->getTable()}
            WHERE created_at IS NOT NULL
            GROUP BY created_at
            ORDER BY import_id DESC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        /** @var Collection $result */
        $result = new Collection;

        foreach ($statement as $row) {
            $result->add([
                'import_id' => $row['import_id'],
                'created_at' => new \Alaress_Date_Format($row['created_at'], \Alaress_Date_Format::DATABASE_FORMAT)
            ]);
        }

        return $result;
    }    
}
