<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Term;

use Alaress_Date_Format;
use RangeException;
use Schoolbox\Mapper as BaseMapper;
use UnexpectedValueException;

/**
 * Mapper for terms.
 *
 * @package Schoolbox\Assessment\Term
 * @method Model createModel()
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{
    /**
     * Sets up mapping for this model.
     */
    public function mapping()
    {
        $this->hasTable('term');
        $this->hasPrototype(new Model());
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('start', self::TYPE_DATE);
        $this->uses('finish', self::TYPE_DATE);
        $this->uses('archived', self::TYPE_BOOLEAN);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyNoOverlap']);
    }

    /**
     * Verifies this term doesn't overlap any other terms.
     *
     * @param  Model $model
     * @return bool Always true, as long as it doesn't throw
     * @throws RangeException if it overlaps
     */
    public function verifyNoOverlap(Model $model)
    {
        // if any term that isn't this one:
        // * finish <= this start
        // * start >= this finish
        // error out
        $query = "
            SELECT * FROM {$this->getTable()}
            WHERE (
                (`start`  BETWEEN CAST(:thisStart AS DATE) AND CAST(:thisFinish AS DATE))
                OR
                (`finish` BETWEEN CAST(:thisStart AS DATE) AND CAST(:thisFinish AS DATE))
            )
        ";
        $params = [
            'thisStart'  => $model->getStart()->getDatabaseDate(),
            'thisFinish' => $model->getFinish()->getDatabaseDate(),
        ];
        if ($model->getId()) {
            $query .= ' AND id != :id';
            $params['id'] = $model->getId();
        }
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        $row = $statement->fetch();
        if ($row !== false) {
            throw new RangeException(
                sprintf(
                    'This term overlaps term "%s" (%s).',
                    $row['title'],
                    $row['code']
                )
            );
        }
        return true;
    }
}
