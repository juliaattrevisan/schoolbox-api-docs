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
use Eportfolio;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\User;

class ShowcaseMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('eportfolio_showcase_item');
		$this->hasModel('\\Eportfolio\\Showcase');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));

		$this->hasMap(array(
			'column'     => 'submission_box_id',
			'property'   => 'submissionBox',
			'type'       => self::TYPE_MODEL,
            'join'       => 'submissionBox'
		));

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'uid',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updatedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_DATETIME,
		));

        $this->hasJoin('submissionBox', array(
            'multiple'  => true,
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBoxMapper',
            'this_key'  => 'submission_box_id',
            'other_key' => 'id',
        ));
	}

    /**
     * Get all the showcased submission boxes for a particular user,
     * ordered alphabetically by their parent category name
     *
     * @param User $user
     * @return Collection
     */
	public function getShowcasedSubmissionBoxes(User $user) {
        $submissionBoxMapper = $this->getJoinMapper('submissionBox');
        $folderMapper = $submissionBoxMapper->getJoinMapper('category');
        $workTypeMapper = $submissionBoxMapper->getJoinMapper('work_type');

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
            LEFT JOIN `category` AS `f` ON `sb`.`category` = `f`.`id`
            WHERE si.uid = :userId
            ORDER BY f.name
        ";

		$statement = $this->database->prepare($query);

		$statement->bindParam(":userId", $user->getId());
		$statement->execute();

        $allItems = new Collection();

        foreach ($statement as $row) {
            $showcasedWorkItem = $this->hydrate($row, 'si');
            $submissionBox = $submissionBoxMapper->hydrate($row, 'sb');
            $workType = $workTypeMapper->hydrate($row, 't');
            $folder = $folderMapper->hydrate($row, 'f');

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
     * @param int $limit
     * @return Schoolbox\Collection
     */
	public function getRecentShowcasedSubmissionBoxes(User $user, $limit = 5) {
        $submissionBoxMapper = $this->getJoinMapper('submissionBox');
        $folderMapper = $submissionBoxMapper->getJoinMapper('category');
        $workTypeMapper = $submissionBoxMapper->getJoinMapper('work_type');

        $statement = $this->database->prepare("
            SELECT
                {$this->getColumnString('si')},
                {$submissionBoxMapper->getColumnString('sb')},
                {$folderMapper->getColumnString('f')},
                {$workTypeMapper->getColumnString('t')}
            FROM `{$this->table}` si
            LEFT JOIN {$this->getJoinSql('submissionBox', 'si', 'sb')}
            LEFT JOIN {$submissionBoxMapper->getJoinSql('work_type', 'sb', 't')}
            LEFT JOIN `category` AS `f` ON `sb`.`category` = `f`.`id`
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
            $submissionBox = $submissionBoxMapper->hydrate($row, 'sb');
            $workType = $workTypeMapper->hydrate($row, 't');
            $folder = $folderMapper->hydrate($row, 'f');

            $submissionBox->setCategory($folder);
            $submissionBox->setWorkType($workType);
            $showcasedWorkItem->setSubmissionBox($submissionBox);

            $allItems->add($showcasedWorkItem);
        }

        return $allItems;
	}
}