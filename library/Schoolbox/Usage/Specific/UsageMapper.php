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


class UsageMapper extends \Schoolbox\Resource\FileMapper {

	/**
	 * The user to generate stats for
	 * * @var int
	 */
	private $userId = 0;

	/**
	 * Constructor
	 *
	 * @param $userId
	 */
	public function __construct($userId) {

		/* Urgh.
		 */
		parent::__construct();

		$this->setUserId($userId);
	}

	/**
	 * The total number of users
	 *
	 * @return mixed
	 */
	public function numFiles() {
		$query = "
			SELECT COUNT(`user`.id) AS num
			FROM `user`
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

		/* TODO: Include submission_return which normally doesn't have mimetype
		 */

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
				AND mime_type LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `file`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `file`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'audio/%')

				UNION

				(SELECT 'Images' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'image/%')
				UNION
				(SELECT 'Video' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `myFile`
				WHERE `owner` = {$userId}
				AND mime_type LIKE 'video/%')
				UNION
				(SELECT 'Audio' AS `name`, COALESCE(SUM(file_size), 0) AS bytes
				FROM `myFile`
				WHERE `owner` = {$userId}
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
			) AS allTotals
			GROUP BY `name`
			ORDER BY bytes DESC
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
				GROUP BY `owner`)
				UNION
				(SELECT 'My Files' AS `name`, SUM(file_size) AS `bytes`
				FROM `myFile`
				WHERE `owner`= {$userId}
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
				GROUP BY `owner`)) AS allTotals
			ORDER BY `bytes` DESC
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
	 * Find total user usage
	 *
	 * This does NOT summate files which have been marked as softdeleted in the db
	 *
	 * @param string $orderBy
	 * @param string $order
	 * @param int $index
	 * @param $usersPerPage
	 * @return array
	 */
	public function findUserUsage($orderBy, $order, $index, $usersPerPage) {
//		$query = "
//			SELECT
//				`user`.firstname, `user`.lastname,
//				CONCAT(`user`.firstname, ' ', `user`.lastname) AS fullname,
//				`groups`.name,
//				COALESCE(SUM(bytes), 0) AS bytes
//			FROM `user`
//			INNER JOIN `groups` ON `user`.group_id = `groups`.id
//			LEFT JOIN
//			(
//				(SELECT owner AS userId, SUM(file_size) AS bytes
//				FROM `file`
//				WHERE deleted = 0
//				GROUP BY owner)
//				UNION
//				(SELECT owner AS userId, SUM(file_size) AS bytes
//				FROM `myFile`
//				WHERE deleted = 0
//				GROUP BY owner)
//				UNION
//				(SELECT uploaded_by AS userId, SUM(filesize) AS bytes
//				FROM `storage`
//				GROUP BY uploaded_by)
//				UNION
//				(SELECT owner AS userId, SUM(filesize) AS bytes
//				FROM `student_submission`
//				GROUP BY owner)
//				UNION
//				(SELECT owner AS userId, SUM(filesize) AS bytes
//				FROM `submission_return`
//				GROUP BY owner)
//			) allTotals ON `user`.id = allTotals.userId
//			GROUP BY `user`.id
//		";
//
//		/* Select order by
//		 */
//		if (!in_array($orderBy, array('firstname', 'lastname', 'name', 'bytes'))) {
//			$orderBy = 'lastname';
//		}
//
//		if (!in_array($order, array('DESC', 'ASC'))) {
//			$order = 'ASC';
//		}
//
//		/* Cast Away
//		 */
//		$index = (int)$index;
//		$usersPerPage = (int)$usersPerPage;
//
//		/* Pagination
//		 */
//		$start = $index * $usersPerPage;
//
//		$query .= "
//			ORDER BY $orderBy $order
//			LIMIT $start, $usersPerPage
//		";
//
//		/* Spit it out
//		 */
//		$statement = $this->database->prepare($query);
//		$statement->execute();
//
//		$result = array();
//
//		while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
//			$result[] = $row;
//		}
//
//		return $result;
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