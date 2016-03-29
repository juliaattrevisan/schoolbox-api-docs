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
        $this->uses('display_finish', self::TYPE_DATE);
        $this->uses('actual_finish', self::TYPE_DATE);
        $this->uses('archived', self::TYPE_BOOLEAN);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyTitle']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyCode']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyDates']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyNoOverlap']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateActualFinishDates']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'updateActualFinishDates']);
    }

    /**
     * Finds whichever term is the current one.
     *
     * @return Model
     */
    public function findForToday()
    {
        return $this->findForDate(Alaress_Date_Format::now());
    }

    /**
     * Find whichever term is current for this date.
     *
     * @param Alaress_Date_Format $date Get the term for this date.
     *
     * @return Model
     */
    public function findForDate(Alaress_Date_Format $date)
    {
        $query = "
            SELECT   {$this->getColumnString()}
            FROM     {$this->getTable()}
            WHERE    `start` <= :date
            ORDER BY `start` DESC
            LIMIT 1
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(['date' => $date->getDatabaseDate(),]);
        $result = $statement->fetch();
        if ($result) {
            return $this->hydrate($result);
        }
        // Nothing? Either throw exception or return an empty item
        return $this->createModel();
    }

    /**
     * Retrieves whichever term is the next term opening after this one.
     *
     * @param Model $term
     * @return Model
     */
    public function findNext(Model $term)
    {
        $endDate = $term->getDisplayFinish();
        if ($endDate instanceof Alaress_Date_Format && !$endDate->isNull()) {
            $query = "
                SELECT   {$this->getColumnString()}
                FROM     {$this->getTable()}
                WHERE    `start` > :date
                ORDER BY `start` ASC
                LIMIT 1
            ";
            $statement = $this->getDatabase()->prepare($query);
            $statement->execute(['date' => $endDate->getDatabaseDate(),]);
            $result = $statement->fetch();
            if ($result) {
                return $this->hydrate($result);
            }
        }
        return $this->createModel();
    }

    /**
     * Verifies this term's title is unique and not empty.
     *
     * @param  Model $model
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if it isn't unique
     */
    public function verifyTitle(Model $model)
    {
        if (strlen($model->getTitle()) === 0) {
            throw new UnexpectedValueException('A term must have a title.');
        }
        if (!$this->isUnique(['title' => $model->getTitle()], $model->getId())) {
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
     * Verifies this term's code is unique and not empty.
     *
     * @param  Model $model
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if it isn't unique
     */
    public function verifyCode(Model $model)
    {
        if (strlen($model->getCode()) === 0) {
            throw new UnexpectedValueException('A term must have a code.');
        }
        if (!preg_match('/^[a-z0-9\\-_]+$/i', $model->getCode())) {
            throw new UnexpectedValueException(
                'A code may only contain alphanumeric, hyphen and underscore characters.'
            );
        }
        if (!$this->isUnique(['code' => $model->getCode()], $model->getId())) {
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
        if (!($model->getDisplayFinish() instanceof Alaress_Date_Format && !$model->getDisplayFinish()->isNull())) {
            throw new UnexpectedValueException('Term finish must be a valid date.');
        }
        if ($model->getStart()->gte($model->getDisplayFinish())) {
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
                (`start`          BETWEEN CAST(:thisStart AS DATE) AND CAST(:thisFinish AS DATE))
                OR
                (`display_finish` BETWEEN CAST(:thisStart AS DATE) AND CAST(:thisFinish AS DATE))
            )
        ";
        $params = [
            'thisStart'  => $model->getStart()->getDatabaseDate(),
            'thisFinish' => $model->getDisplayFinish()->getDatabaseDate(),
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

    /**
     * Sets the actual finish date for all terms as appropriate.
     */
    public function updateActualFinishDates()
    {
        $updateTermQuery = $this->getDatabase()->prepare(
            "UPDATE {$this->getTable()} SET `actual_finish` = :actual_finish WHERE `id` = :id"
        );
        $selectActualFinishQuery = $this->getDatabase()->query("
            SELECT `current`.`id`, (MIN(`next`.`start`) - INTERVAL 1 DAY) AS `actual_finish`
            FROM {$this->getTable()} `current`
            LEFT JOIN {$this->getTable()} `next` ON `current`.`display_finish` < `next`.`start`
            GROUP BY `current`.`id`;
        ");
        foreach ($selectActualFinishQuery->fetchAll() as $row) {
            $updateTermQuery->execute([
                'id'            => $row['id'],
                'actual_finish' => $row['actual_finish'],
            ]);
        }
    }
}
