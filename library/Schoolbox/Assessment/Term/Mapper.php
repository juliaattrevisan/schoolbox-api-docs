<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Term;

use Alaress_Date_Format;
use PDOException;
use PDOStatement;
use RangeException;
use Schoolbox\Column\Date;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\YearLevel;
use UnexpectedValueException;

/**
 * Mapper for terms.
 *
 * @package Schoolbox\Assessment\Term
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection|Model[] findAll($options = array())
 */
class Mapper extends BaseMapper
{
    /**
     * Sets up mapping for this model.
     */
    public function mapping()
    {
        // DB stores date-only, but code-base includes time to simplify comparisons.
        $startOfDay = Date::startOfDay();
        $endOfDay   = Date::endOfDay();

        $this->hasTable('term');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('start', $startOfDay);
        $this->uses('display_finish', $endOfDay);
        $this->uses('actual_finish', $endOfDay);
        $this->uses('archived', self::TYPE_BOOLEAN);

        $this->joinsVia(
            'term_year_level',
            YearLevel\Mapper::class,
            'id',
            'year_level_id',
            'term_id',
            null,
            null,
            null,
            'yearLevels'
        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyTitle']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyCode']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyDates']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyNoOverlap']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateActualFinishDates']);
        $this->registerCallback(self::CALLBACK_PREDELETE, [$this, 'deleteRelations']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'updateActualFinishDates']);
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    protected function getSelectString()
    {
        $yearLevelMapper = YearLevel\Mapper::getInstance();

        return "
            SELECT
                {$this->getColumnString()},
                {$yearLevelMapper->getColumnString('lyl')}
        ";
    }


    /**
     * @inheritDoc
     *
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('term_year_level', '', 'lyl')}
        ";
    }


    /**
     * @inheritDoc
     *
     * @param PDOStatement $statement
     * @return GenericCollection|void
     * @throws BaseMapper\DefinitionException
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var YearLevel\Mapper $yearLevelMapper */
        $yearLevelMapper = $this->getJoinMapper('term_year_level');

        /**
         * Light-weight cache for efficiency, indexed by YearLevel.id
         * @var YearLevel\Model[] $yearLevelCache
         */
        $yearLevelCache = [];

        $terms = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $termId = (int)$row['id'];

            if (!$terms->offsetExists($termId)) {
                $terms[$termId] = $this->hydrate($row);
                $terms[$termId]->setYearLevels($yearLevelMapper->createCollection());
            }

            $yearLevelId = $row['lyl.' . YearLevel\Mapper::COL_ID];

            if ($yearLevelId) {
                if (!array_key_exists($yearLevelId, $yearLevelCache)) {
                    $yearLevelCache[$yearLevelId] = $yearLevelMapper->hydrate($row, 'lyl');
                }

                $terms[$termId]->getYearLevels()->setOffset($yearLevelId, $yearLevelCache[$yearLevelId]);
            }
        }

        return $terms;
    }


    /**
     * Find the first extraordinary Term associated with $yearLevels instances,
     * whose $date is in between the start and display_finish values, and is not
     * archived (therefore the extraordinary Term is currently in progress)
     *
     * Extraordinary Terms are only associated with one Year Level
     * Any other configuration of relations (0 or 2+) results in default behaviour
     * (find the first non-extraordinary Term)
     *
     * @param Alaress_Date_Format  $date
     * @param YearLevel\Model|null $yearLevel
     * @return Model
     * @throws BaseMapper\DefinitionException
     * @throws Exception
     * @throws PDOException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findForDateAndYearLevel(Alaress_Date_Format $date, ?\Schoolbox\YearLevel\Model $yearLevel): Model
    {

        /* No associated Year Levels; do the usual default behaviour
         */
        if (null === $yearLevel) {
            return $this->findForDate($date);
        }

        /* Only seek one determinate Extraordinary Term
         *
         * Any other configuration leads to default findForDate() as we can't conclude
         * which extraordinary Term should be returned...
         */
        $query = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('term_year_level', 'lyl')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('term_year_level', '', 'lyl')}
            WHERE lyl.id = :yearLevelId
            AND start <= :date
            AND display_finish > :date
            ORDER BY start DESC
        ";

        $statement = $this->getDatabase()->prepare($query);

        $statement->execute([
            'yearLevelId' => $yearLevel->getId(),
            'date' => $date->getDatabaseDate()
        ]);

        $term = $this->hydrateSelect($statement)->first();

        /* The first outcome:
         * Found an extraordinary Term for the Year Level specified
         * It's already begun, it hasn't already finished, it's not archived,
         * Therefore applicable
         *
         * The second outcome:
         * Year Level specified, but we didn't find any non-archived extraordinary Terms
         * for the date provided, in any of the Terms that exist with Year Level relations
         * An extraordinary Term might not have begun, or has already ended
         * Use the default behaviour
         */
        return $term ?? $this->findForDate($date);
    }


    /**
     * Find all the active folder ids related to $term
     *
     * @param Model                $term
     * @return array
     * @throws PDOException
     */
    public function findFolderIdsForTerm(Model $term): array
    {
        $query = "
            SELECT DISTINCT folder_id
            FROM folder_year_level fyl
            INNER JOIN term_year_level tyl ON fyl.year_level_id = tyl.year_level_id
            INNER JOIN {$this->getTable()} t ON tyl.term_id = t.{$this->getIdentifierColumn()}
            INNER JOIN folder ON fyl.folder_id = folder.id
            WHERE t.{$this->getIdentifierColumn()} = :termId
            AND folder.deleted_at IS NULL
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'termId' => $term->getId()
        ]);

        $folderIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $folderIds[$row['folder_id']] = $row['folder_id'];
        }

        return $folderIds;
    }


    /**
     * A list of Terms to choose from
     *
     * If we're in an extraordinary Term right now, provide that as an option in
     * addition to the ordinary Terms by prepending it to the returned collection
     *
     * @param YearLevel\Model|null $yearLevel
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findAllWithExtraordinary(?\Schoolbox\YearLevel\Model $yearLevel): GenericCollection
    {
        $terms = $this->findAllNonExtraordinary();
        $extraordinaryTerm = $this->findForDateAndYearLevel(\Alaress_Date_Format::now(), $yearLevel);

        if ($extraordinaryTerm->getExtraordinary()) {
            $terms->prepend($extraordinaryTerm);
        }

        return $terms;
    }


    /**
     * Find all the non-extraordinary Terms, ordered by start date
     *
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findAllNonExtraordinary(): GenericCollection
    {
        return $this->findAll()->filter(static function (Model $model) {
            return !$model->getExtraordinary();
        })->sortBy('start', 'desc');
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
     * @throws BaseMapper\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findForDate(Alaress_Date_Format $date): Model
    {
        $pb = $this->getPredicateBuilder()

            /* Term must have started already
             */
            ->lessThanOrEqualTo('start', $date->getDatabaseDate());

            /* Why isn't Archived one of the settings here?
             *
             * because: an Archived Term is still functional and still relevant, you just don't
             * add or do anything with them other than see what happened previously
             */

        /* Find the first ordinary Term
         */
        $term = $this->findByPredicates($pb, [
            'order' => ['start' => 'DESC']
        ])->filter(static function (Model $model) {
            return !$model->getExtraordinary();
        })->first();

        return $term ?? $this->createModel();
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
            throw new UnexpectedValueException('This term must have a title.');
        }
        if (!$this->isUnique(['title' => $model->getTitle()], $model->getId())) {
            throw new UnexpectedValueException(
                sprintf(
                    'A term with the title "%s" already exists, you must have a unique title',
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
            throw new UnexpectedValueException('This term must have a code.');
        }
        if (!preg_match('/^[a-z0-9\\-_]+$/i', $model->getCode())) {
            throw new UnexpectedValueException(
                'You may only use contain alphanumeric, hyphen and underscore characters in your code.'
            );
        }
        if (!$this->isUnique(['code' => $model->getCode()], $model->getId())) {
            throw new UnexpectedValueException(
                sprintf(
                    'A term with this code "%s" already exists, you must have a unique code',
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
            throw new UnexpectedValueException('The term start date is not a valid date.');
        }
        if (!($model->getDisplayFinish() instanceof Alaress_Date_Format && !$model->getDisplayFinish()->isNull())) {
            throw new UnexpectedValueException('The term end date is not a valid date.');
        }
        if ($model->getStart()->gte($model->getDisplayFinish())) {
            throw new UnexpectedValueException('The term start must be before term finish.');
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

        /* Bypass validation checks if you associated it with n Year levels
         * This creates an extraordinary Term, beyond the normal sequence of Terms.
         */
        if ($model->getYearLevels() && $model->getYearLevels()->count()) {
            return true;
        }

        // if any term that isn't this one:
        // * overlaps (finish <= this start)
        // * overlaps (start >= this finish)
        // * covers (start < this start, finish > this finish)
        // * is covered by (start >= this start, finish <= this finish)
        // error out
        $query = "
            SELECT * FROM {$this->getTable()}
            LEFT JOIN term_year_level tyl ON term.id = tyl.term_id
            WHERE (
                (`start`          BETWEEN CAST(:thisStart AS DATE) AND CAST(:thisFinish AS DATE))
                OR
                (`display_finish` BETWEEN CAST(:thisStart AS DATE) AND CAST(:thisFinish AS DATE))
                OR
                (`start` < CAST(:thisStart AS DATE) AND `display_finish` > CAST(:thisFinish AS DATE))
                OR
                (`start` >= CAST(:thisStart AS DATE) AND `display_finish` <= CAST(:thisFinish AS DATE))
            )
            AND term_id IS NULL
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
                    'Terms must not overlap other terms. This term is overlapping "%s" (%s).',
                    $row['title'],
                    $row['code']
                )
            );
        }
        return true;
    }

    /**
     * Update all Ordinary Terms (these are Terms with zero Year Level relations) so that
     * their start and actual_finish dates are continuous. Other entities in schoolbox often
     * depend on existing within these date ranges, so if you have a gap you'll be unable
     * to access such entities.
     */
    public function updateActualFinishDates(): void
    {
        $query = "
            SELECT id
            FROM {$this->getTable()}
            LEFT JOIN term_year_level tyl ON {$this->getTable()}.{$this->getIdentifierColumn()} = tyl.term_id
            WHERE tyl.term_id IS NULL
            ORDER BY `start`
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        $ids = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        /* If there aren't any ordinary terms, just leave; there's nothing to do
         * Although, I don't expect this to ever be the case, it's possible that a school
         * could be using an Extraordinary Term as its one and only term instance
         */
        if (!\count($ids)) {
            return;
        }

        /* Only update the ordinary terms
         */
        $predicate = \Alaress\Database\Predicate\Builder::getArrayBindings($ids, 'i');

        $updateTermQuery = $this->getDatabase()->prepare(
            "UPDATE {$this->getTable()} SET `actual_finish` = :actual_finish WHERE `id` = :id"
        );

        $selectActualFinishQuery = $this->getDatabase()->query("
            SELECT `current`.`id`, (MIN(`next`.`start`) - INTERVAL 1 DAY) AS `actual_finish`
            FROM {$this->getTable()} `current`
            LEFT JOIN {$this->getTable()} `next` ON `current`.`display_finish` < `next`.`start`
            WHERE `current`.`id` IN ({$predicate['placeholders']}) AND `next`.`id` IN ({$predicate['placeholders']})
            GROUP BY `current`.`id`;
        ", $predicate['bindings']);

        foreach ($selectActualFinishQuery->fetchAll() as $row) {
            $updateTermQuery->execute([
                'id'            => $row['id'],
                'actual_finish' => $row['actual_finish'],
            ]);
        }

        /* Set the last ordinary term's actual_finish to null, for enforced continuity
         */
        $updateTermQuery->execute([
            'id'            => $ids[\count($ids) - 1],
            'actual_finish' => null,
        ]);
    }


    /**
     * Delete relations for the Term, then delete the Term itself
     *
     * @param Model $model
     * @throws PDOException
     */
    public function deleteRelations(Model $model): void
    {
        $query = "
            DELETE FROM term_year_level
            WHERE term_id = :termId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'termId' => $model->getId()
        ]);
    }
}
