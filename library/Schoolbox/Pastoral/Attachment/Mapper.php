<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Attachment;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachmentModel;

class Mapper extends BaseMapper
{

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_attachment');
        $this->hasModel(PastoralAttachmentModel::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('hash', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Retrieves all records relating to a particular pastoral record
     *
     * @param  int $pastoralId
     *
     * @return \Schoolbox\Collection
     */
    public function findByPastoralId($pastoralId) {
        $collection = new Collection();

        $query = "
			SELECT  {$this->getColumnString('p')}
			FROM    `{$this->getTable()}` `p`
			WHERE   `p`.`deleted_at` IS NULL AND
				    `p`.`pastoral_id` = :pastoralId
			";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':pastoralId', $pastoralId);
        $statement->execute();

        foreach ($statement as $row) {
            $pastoral = $this->hydrate($row, 'p');
            $collection->add($pastoral);
        }

        return $collection;
    }

}
