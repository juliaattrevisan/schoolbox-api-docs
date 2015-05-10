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
	 * Find total usage
	 *
	 * This summates the filesizes of all files in the specified location.
	 * This includes files which may have been marked as softdeleted in the db
	 */
	public function findTotalUsage() {
		$userId = $this->getUserId();

		$query = "
			SELECT COALESCE(SUM(bytes), 0) AS bytes
			FROM
			(
				(SELECT SUM(file_size) AS bytes
				FROM `file`
				WHERE `owner` = {$userId}
				AND deleted = 0
				GROUP BY `owner`)
				UNION
				(SELECT SUM(file_size) AS bytes
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND deleted = 0
				GROUP BY `owner`)
				UNION
				(SELECT SUM(filesize) AS bytes
				FROM `storage`
				WHERE `uploaded_by` = {$userId}
				GROUP BY `uploaded_by`)
				UNION
				(SELECT SUM(filesize) AS bytes
				FROM `student_submission`
				WHERE `owner` = {$userId}
				GROUP BY `owner`)
				UNION
				(SELECT SUM(filesize) AS bytes
				FROM `submission_return`
				WHERE `owner` = {$userId}
				GROUP BY `owner`)
			) AS allTotals
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		$result = $statement->fetch(\PDO::FETCH_ASSOC);

		return $result['bytes'];
	}

	/**
	 * The total number of files
	 *
	 * @return mixed
	 */
	public function numFiles() {
		$userId = $this->getUserId();

		$query = "
			SELECT COALESCE(SUM(num), 0) AS num
			FROM
			(
				(SELECT COUNT(*) AS num
				FROM `file`
				WHERE `owner` = {$userId}
				AND deleted = 0
				GROUP BY `owner`)
				UNION
				(SELECT COUNT(*) AS num
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND deleted = 0
				GROUP BY `owner`)
				UNION
				(SELECT COUNT(*) AS num
				FROM `storage`
				WHERE `uploaded_by` = {$userId}
				GROUP BY `uploaded_by`)
				UNION
				(SELECT COUNT(*) AS num
				FROM `student_submission`
				WHERE `owner` = {$userId}
				GROUP BY `owner`)
				UNION
				(SELECT COUNT(*) AS num
				FROM `submission_return`
				WHERE `owner` = {$userId}
				GROUP BY `owner`)
			) AS allTotals
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		$result = $statement->fetch(\PDO::FETCH_ASSOC);

		return $result['num'];
	}

	/**
	 * Find total category usage
	 *
	 * This does NOT summate files which have been marked as softdeleted in the db
	 *
	 * @return array
	 */
	public function findGroupUsage() {
		$userId = $this->getUserId();

		/* Just Like Voltron
		 */
		$query = "
			SELECT `name`, SUM(bytes) AS bytes
			FROM
			(
				(SELECT 'Images' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `file`
				WHERE `owner` = {$userId}
				AND deleted = 0
				AND mime_type LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `file`
				WHERE `owner` = {$userId}
				AND deleted = 0
				AND mime_type LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `file`
				WHERE `owner` = {$userId}
				AND deleted = 0
				AND mime_type LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND deleted = 0
				AND mime_type LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND deleted = 0
				AND mime_type LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND deleted = 0
				AND mime_type LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `storage`
				WHERE `uploaded_by` = {$userId}
				AND mime LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `storage`
				WHERE `uploaded_by` = {$userId}
				AND mime LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `storage`
				WHERE `uploaded_by` = {$userId}
				AND mime LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `student_submission`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `student_submission`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `student_submission`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `submission_return`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `submission_return`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(filesize), 0) AS bytes
				FROM `submission_return`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'audio/%')
			) AS allTotals
			GROUP BY `name`
			ORDER BY bytes DESC
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

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
		$userId = $this->getUserId();

		$query = "
			SELECT *
			FROM
				((SELECT 'Files' AS `name`, SUM(file_size) AS `bytes`
				FROM `file`
				WHERE `owner`= {$userId}
				AND deleted = 0
				GROUP BY `owner`)
				UNION
				(SELECT 'My Files' AS `name`, SUM(file_size) AS `bytes`
				FROM `myFile`
				WHERE `owner`= {$userId}
				AND deleted = 0
				GROUP BY `owner`)
				UNION
				(SELECT 'Storage' AS `name`, SUM(filesize) AS `bytes`
				FROM `storage`
				WHERE `uploaded_by` = {$userId}
				GROUP BY `uploaded_by`)
				UNION
				(SELECT 'Submission'  AS `name`, SUM(filesize) AS `bytes`
				FROM `student_submission`
				WHERE `owner` = {$userId}
				GROUP BY `owner`)
				UNION
				(SELECT 'Returns'  AS `name`, SUM(filesize) AS `bytes`
				FROM `submission_return`
				WHERE `owner` = {$userId}
				AND deleted = 0
				GROUP BY `owner`)) AS allTotals
			ORDER BY `bytes` ASC
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

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
		$userId = $this->getUserId();

		$query = "
			SELECT *
			FROM
			(
				(SELECT
				    file.id,
                    realname AS `name`,
                    'File' AS groupName,
                    file_size AS bytes,
                    mime_type,
                    '' AS hash,
                    category.id AS categoryId,
                    category.name AS categoryName
				FROM `file`
				LEFT JOIN category ON file.category = category.id
				WHERE `owner` = {$userId}
				AND deleted = 0)
				UNION
				(SELECT
				    id,
                    realname AS `name`,
                    'My File' AS groupName,
                    file_size AS bytes,
                    mime_type,
                    '' AS hash,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND deleted = 0)
				UNION
				(SELECT
				    '' AS id,
                    filename AS `name`,
                    'Storage' AS groupName,
                    filesize AS bytes,
                    mime AS mime_type,
                    hash,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `storage`
				WHERE `uploaded_by` = {$userId})
				UNION
				(SELECT
				    '' AS id,
                    realname AS `name`,
                    'Submission' AS groupName,
                    filesize AS bytes,
                    mime_type,
                    '' AS hash,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `student_submission`
				WHERE `owner` = {$userId})
				UNION
				(SELECT
				    '' AS id,
                    filename AS `name`,
                    'Return' AS groupName,
                    filesize AS bytes,
                    mime_type,
                    '' AS hash,
                    '' AS categoryId,
                    '' AS categoryName
				FROM `submission_return`
				WHERE `owner` = {$userId}
				AND deleted = 0)
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
		$statement->execute();

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