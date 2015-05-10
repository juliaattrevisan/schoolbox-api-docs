<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Usage\Specific;

use Database;

class UsageMapper
{

	/**
	 * The user to generate stats for
	 * * @var int
	 */
	private $userId = 0;

    /**
     * @var Database
     */
    protected $database;

	/**
	 * Constructor
	 *
	 * @param $userId
	 */
	public function __construct($userId) {

		/* Urgh.
		 */
//		parent::__construct();

        $this->database = Database::getInstance();

		$this->setUserId($userId);
	}

    /**
     * Gets usage bytes and total files as part of a single query.
     *
     * @return array bytes: bytes used, num: total files
     */
    protected function getUsage()
    {
        $query = "
			SELECT
			    COALESCE(SUM(bytes), 0) AS bytes,
			    COALESCE(SUM(num), 0) AS num
			FROM
			(
				(SELECT SUM(`s`.`filesize`) AS bytes, COUNT(*) AS num
				FROM `file` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				GROUP BY `f`.`owner`)
				UNION
				(SELECT SUM(`s`.`filesize`) AS bytes, COUNT(*) AS num
				FROM `myFile` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				GROUP BY `f`.`owner`)
				UNION
				(SELECT SUM(filesize) AS bytes, COUNT(*) AS num
				FROM `storage`
				WHERE `uploaded_by` = :userId
				GROUP BY `uploaded_by`)
				UNION
				(SELECT SUM(`s`.`filesize`) AS bytes, COUNT(*) AS num
				FROM `student_submission` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				GROUP BY `f`.`owner`)
				UNION
				(SELECT SUM(`s`.`filesize`) AS bytes, COUNT(*) AS num
				FROM `submission_return` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				GROUP BY `f`.`owner`)
			) AS allTotals
		";
        $statement = $this->database->prepare($query);
        $statement->execute(['userId' => $this->getUserId()]);
        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

	/**
	 * Find total usage
	 *
	 * This summates the filesizes of all files in the specified location.
	 * This includes files which may have been marked as softdeleted in the db
	 */
	public function findTotalUsage() {
        return $this->getUsage()['bytes'];
	}

	/**
	 * The total number of files
	 *
	 * @return mixed
	 */
	public function numFiles() {
        return $this->getUsage()['num'];
	}

	/**
	 * Find total category usage
	 *
	 * This does NOT summate files which have been marked as softdeleted in the db
	 *
	 * @return array
	 */
	public function findGroupUsage() {
		/* Just Like Voltron
		 */
		$query = "
			SELECT `name`, SUM(bytes) AS bytes
			FROM
			(
				(SELECT 'Images' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `file` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				AND `s`.`mime` LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `file` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				AND `s`.`mime` LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `file` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				AND `s`.`mime` LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `myFile` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				AND `s`.`mime` LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `myFile` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				AND `s`.`mime` LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `myFile` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				AND `s`.`mime` LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `storage`
				WHERE `uploaded_by` = :userId
				AND mime LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `storage`
				WHERE `uploaded_by` = :userId
				AND mime LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `storage`
				WHERE `uploaded_by` = :userId
				AND mime LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `student_submission` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `s`.`mime` LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `student_submission` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `s`.`mime` LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `student_submission` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `s`.`mime` LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `submission_return` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `s`.`mime` LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `submission_return` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `s`.`mime` LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(`s`.`filesize`), 0) AS bytes
				FROM `submission_return` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `s`.`mime` LIKE 'audio/%')
			) AS allTotals
			GROUP BY `name`
			ORDER BY bytes DESC
		";

		$statement = $this->database->prepare($query);
		$statement->execute(['userId' => $this->getUserId()]);

		$result = array();
		$mediaBytes = 0;

		while($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$mediaBytes += $row['bytes'];
			$result[] = $row;
		}

		/* Find all the documents this User has everywhere, and subtract
		 * the megaquery's result to find out non-media counts
		 */
		$allData = $this->findTypeUsage();
		$totalBytes = 0;

		foreach($allData as $currentData) {
			$totalBytes += $currentData['bytes'];
		}

		/* Append extra doc row
		 */
		$docBytes = array(
			'name' => 'Documents',
			'bytes' => $totalBytes - $mediaBytes
		);

		array_unshift($result, $docBytes);

		return $result;
	}

	/**
	 * Find total type usage
	 *
	 * This summates the filesizes of all files in the specified location.
	 * This includes files which may have been marked as softdeleted in the db
	 *
	 * @return array
	 */
	public function findTypeUsage() {
		$query = "
			SELECT *
			FROM
				((SELECT 'Files' AS `name`, SUM(`s`.`filesize`) AS `bytes`
				FROM `file` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				GROUP BY `f`.`owner`)
				UNION
				(SELECT 'My Files' AS `name`, SUM(`s`.`filesize`) AS `bytes`
				FROM `myFile` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				GROUP BY `f`.`owner`)
				UNION
				(SELECT 'Storage' AS `name`, SUM(filesize) AS `bytes`
				FROM `storage`
				WHERE `uploaded_by` = :userId
				GROUP BY `uploaded_by`)
				UNION
				(SELECT 'Submission'  AS `name`, SUM(`s`.`filesize`) AS `bytes`
				FROM `student_submission` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				GROUP BY `f`.`owner`)
				UNION
				(SELECT 'Returns'  AS `name`, SUM(`s`.`filesize`) AS `bytes`
				FROM `submission_return` `f`
				INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0
				GROUP BY `f`.`owner`)) AS allTotals
			ORDER BY `bytes` ASC
		";

		$statement = $this->database->prepare($query);
        $statement->execute(['userId' => $this->getUserId()]);

		$result = array();

		while($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$result[] = $row;
		}

		return $result;
	}

	/**
	 * Find total file usage
	 *
	 * This does NOT summate files which have been marked as softdeleted in the db
	 *
	 * @param string $orderBy
	 * @param string $order
	 * @param int $index
	 * @param $numPerPage
	 * @return array
	 */
	public function findFileUsage($orderBy, $order, $index, $numPerPage) {
        $storageJoin = "INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`";
        $mimeJoin = "
            LEFT JOIN `allowedFileExtension` `e` ON `s`.`ext` = `e`.`extension`
            LEFT JOIN `allowedFileTypes` `t` ON `e`.`fileTypeId` = `t`.`fileTypeId`
        ";
		$query = "
			SELECT *
			FROM
			(
				(SELECT
				    `f`.`id`,
                    `f`.`realname` AS `name`,
                    'File' AS groupName,
                    `s`.`filesize` AS bytes,
                    `t`.`mime` AS mime_type,
                    '' AS hash,
                    `c`.`id` AS categoryId,
                    `c`.`name` AS categoryName
				FROM `file` `f`
				LEFT JOIN category `c` ON `f`.`category` = `c`.`id`
				$storageJoin
				$mimeJoin
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0)
				UNION
				(SELECT
				    `f`.`id`,
                    `f`.`realname` AS `name`,
                    'File' AS groupName,
                    `s`.`filesize` AS bytes,
                    `t`.`mime` AS mime_type,
                    '' AS hash,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `myFile` `f`
				$storageJoin
				$mimeJoin
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0)
				UNION
				(SELECT
				    '' AS id,
                    `s`.`filename` AS `name`,
                    'Storage' AS groupName,
                    `s`.`filesize` AS bytes,
                    `t`.`mime` AS mime_type,
                    `s`.`hash`,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `storage` `s`
				$mimeJoin
				WHERE `s`.`uploaded_by` = :userId)
				UNION
				(SELECT
				    '' AS id,
                    `f`.`realname` AS `name`,
                    'Submission' AS groupName,
                    `s`.`filesize` AS bytes,
                    `t`.`mime` AS mime_type,
                    '' AS hash,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `student_submission` `f`
				$storageJoin
				$mimeJoin
				WHERE `f`.`owner` = :userId)
				UNION
				(SELECT
				    '' AS id,
                    `f`.`filename` AS `name`,
                    'Return' AS groupName,
                    `s`.`filesize` AS bytes,
                    `t`.`mime` AS mime_type,
                    '' AS hash,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `submission_return` `f`
				$storageJoin
				$mimeJoin
				WHERE `f`.`owner` = :userId
				AND `f`.`deleted` = 0)
			) AS allTotals
		";

		/* Select order by
		 */
		if (!in_array($orderBy, array('name', 'groupName', 'bytes', 'mime_type', 'categoryName'))) {
			$orderBy = 'name';
		}

		if (!in_array($order, array('DESC', 'ASC'))) {
			$order = 'ASC';
		}

		/* Cast Away
		 */
		$index = (int)$index;
		$numPerPage = (int)$numPerPage;

		/* Pagination
		 */
		$start = $index * $numPerPage;

		$query .= "
			ORDER BY $orderBy $order
			LIMIT $start, $numPerPage
		";

		/* Spit it out
		 */
		$statement = $this->database->prepare($query);
        $statement->execute(['userId' => $this->getUserId()]);

		$result = array();

		while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$result[] = $row;
		}

		return $result;
	}

	/* Getters/Setters
	 */

	/**
	 * Set user id
	 *
	 * @param int $userId
	 */
	public function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return int
	 */
	public function getUserId() {
		return $this->userId;
	}
}