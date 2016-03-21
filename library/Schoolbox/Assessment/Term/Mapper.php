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
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyTitle']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyCode']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyDates']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyNoOverlap']);
    }

    /**
     * Verifies this term's title is unique.
     *
     * @param  Model $model
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if it isn't unique
     */
    public function verifyTitle(Model $model)
    {
        if (!$this->isUnique('title', $model->getTitle(), $model->getId())) {
            throw new UnexpectedValueException(
                sprintf(
                    'A term with title "%s" already exists.',
                    $model->getTitle()
                )
            );
        }
        return true;
    }

    /**
     * Verifies this term's code is unique.
     *
     * @param  Model $model
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if it isn't unique
     */
    public function verifyCode(Model $model)
    {
        if (!$this->isUnique('code', $model->getCode(), $model->getId())) {
            throw new UnexpectedValueException(
                sprintf(
                    'A term with code "%s" already exists.',
                    $model->getCode()
                )
            );
        }
        return true;
    }

    /**
     * Verifies that a field's value is unique.
     *
     * @param  string $field     The column to compare
     * @param  mixed  $value     The value to compare
     * @param  mixed  $currentId The ID of the row I'm trying to save.
     *                           It's okay if the row I'm trying to save has
     *                           this value already.
     *
     * @return bool
     */
    protected function isUnique($field, $value, $currentId)
    {
        $query = $this->selectScalarByColumnList($this->getIdentifier()['column'], [$field => $value]);
        $query->execute();
        $dupeId = $query->fetchColumn();
        if (!$dupeId) {
            // No duplicate
            return true;
        } elseif ($currentId == $dupeId) {
            // This row already has this value - that's okay
            return true;
        }
        return false;
    }

    /**
     * Verifies this term has proper dates.
     *
     * @param  Model $model
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if dates are invalid
     */
    public function verifyDates(Model $model)
    {
        if (!($model->getStart() instanceof Alaress_Date_Format && !$model->getStart()->isNull())) {
            throw new UnexpectedValueException('Term start must be a valid date.');
        }
        if (!($model->getFinish() instanceof Alaress_Date_Format && !$model->getFinish()->isNull())) {
            throw new UnexpectedValueException('Term finish must be a valid date.');
        }
        if ($model->getStart()->gte($model->getFinish())) {
            throw new UnexpectedValueException('Term start must be before term finish.');
        }
        return true;
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
