<?php
/**
 * Eportfolio_ShowcaseMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;

use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

class ShowcaseMapper extends Schoolbox\Mapper
{

    protected function mapping()
    {
        $this->hasTable('eportfolio_showcase_item');
        $this->hasModel(Showcase::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('submission_box_id', SubmissionBoxMapper::class, 'id', self::TYPE_MODEL, null, 'submissionBox', 'submissionBox');
        $this->uses('uid', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Get all the showcased submission boxes for a particular user,
     * ordered alphabetically by their parent category name
     *
     * @param User $user
     * @return Collection
     */
    public function getShowcasedSubmissionBoxes(User $user)
    {
        $submissionBoxMapper = $this->getJoinMapper('submissionBox');
        $folderMapper        = $submissionBoxMapper->getJoinMapper('category');
        $workTypeMapper      = $submissionBoxMapper->getJoinMapper('work_type');

        /* When you make a Showcased Work item, but then later the Folder gets deleted,
         * the Folder object doesn't get hydrated due to softdelete field. It just comes
         * out as an empty Folder instance, which isn't helpful, as this allows the
         * Showcased Work item to display without a parent heading
         *
         * Doing this joins a whole bunch of Folders that aren't correct:
         *
         * LEFT JOIN `category` AS `c` ON `sb`.`category` = `c`.`id` AND
         * `c`.`deleted_at` IS NULL OR `c`.`deleted_at` IS NOT NULL
         *
         * So until we decide what we do for this use case, I'm just going to override the
         * soft-delete thing and make the Folder instances spawn properly anyway
         */
        $query = "
            SELECT
                {$this->getColumnString('si')},
                {$submissionBoxMapper->getColumnString('sb')},
                {$folderMapper->getColumnString('f')},
                {$workTypeMapper->getColumnString('t')}
            FROM `{$this->table}` si
            LEFT JOIN {$this->getJoinSql('submissionBox', 'si', 'sb')}
            LEFT JOIN {$submissionBoxMapper->getJoinSql('work_type', 'sb', 't')}
            LEFT JOIN `folder` AS `f` ON `sb`.`category` = `f`.`id`
            WHERE si.uid = :userId
            ORDER BY f.name
        ";

        $statement = $this->database->prepare($query);

        $statement->bindParam(":userId", $user->getId());
        $statement->execute();

        $allItems = new Collection();

        foreach ($statement as $row) {
            $showcasedWorkItem = $this->hydrate($row, 'si');
            $submissionBox     = $submissionBoxMapper->hydrate($row, 'sb');
            $workType          = $workTypeMapper->hydrate($row, 't');
            $folder            = $folderMapper->hydrate($row, 'f');

            $submissionBox->setCategory($folder);
            $submissionBox->setWorkType($workType);
            $showcasedWorkItem->setSubmissionBox($submissionBox);

            $allItems->add($showcasedWorkItem);
        }

        return $allItems;
    }


    /**
     * Get all the showcased submission boxes for a particular user,
     * ordered alphabetically by the time they were showcased.
     *
     * This is used on the profile screen
     *
     * @param \Schoolbox\User $user
     * @param int             $limit
     * @return Schoolbox\Collection|Showcase[]
     */
    public function getRecentShowcasedSubmissionBoxes(User $user, $limit = 5)
    {
        $submissionBoxMapper = $this->getJoinMapper('submissionBox');
        $folderMapper        = $submissionBoxMapper->getJoinMapper('category');
        $workTypeMapper      = $submissionBoxMapper->getJoinMapper('work_type');

        $statement = $this->database->prepare("
            SELECT
                {$this->getColumnString('si')},
                {$submissionBoxMapper->getColumnString('sb')},
                {$folderMapper->getColumnString('f')},
                {$workTypeMapper->getColumnString('t')}
            FROM `{$this->table}` si
            LEFT JOIN {$this->getJoinSql('submissionBox', 'si', 'sb')}
            LEFT JOIN {$submissionBoxMapper->getJoinSql('work_type', 'sb', 't')}
            LEFT JOIN `folder` AS `f` ON `sb`.`category` = `f`.`id`
            WHERE si.uid = :userId
            ORDER BY si.created_at DESC
            LIMIT :limit
        ");

        $statement->bindParam(":userId", $user->getId());

        /* Have to do this in a special way, apparently:
         * http://stackoverflow.com/questions/2269840/php-pdo-bindvalue-in-limit
         */
        $statement->bindParam(":limit", $limit, \PDO::PARAM_INT);
        $statement->execute();

        $allItems = new Collection();

        foreach ($statement as $row) {
            $showcasedWorkItem = $this->hydrate($row, 'si');
            $submissionBox     = $submissionBoxMapper->hydrate($row, 'sb');
            $workType          = $workTypeMapper->hydrate($row, 't');
            $folder            = $folderMapper->hydrate($row, 'f');

            $submissionBox->setCategory($folder);
            $submissionBox->setWorkType($workType);
            $showcasedWorkItem->setSubmissionBox($submissionBox);

            $allItems->add($showcasedWorkItem);
        }

        return $allItems;
    }
}
