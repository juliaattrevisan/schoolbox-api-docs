<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Usage;


class UsageMapper extends \Schoolbox\Resource\FileMapper {

	/**
	 * Find total usage
	 */
	public function findTotalUsage() {
		$query = "
			SELECT SUM(bytes) as total
			FROM
				(
				(SELECT SUM(file_size) AS bytes
				FROM `file`
				WHERE deleted = 0)
				UNION
				(SELECT SUM(file_size) AS bytes
				FROM `myFile`
				WHERE deleted = 0)
				UNION
				(SELECT SUM(filesize) AS bytes
				FROM `storage`)
				UNION
				(SELECT SUM(filesize) AS bytes
				FROM `student_submission`)
				UNION
				(SELECT SUM(filesize) AS bytes
				FROM `submission_return`)
			) AS allTotals
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		$result = $statement->fetch(\PDO::FETCH_ASSOC);

		return $result['total'];
	}

	/**
	 * Find total category usage
	 *
	 * @return array
	 */
	public function findGroupUsage() {
		$query = "
			SELECT `groups`.name, COALESCE(SUM(bytes), 0) AS bytes
			FROM groups
			LEFT JOIN
			(
				(SELECT `groups`.id, `groups`.name, SUM(file_size) AS bytes
				FROM `file`
				INNER JOIN `user` ON `user`.id = `file`.owner
				INNER JOIN `groups` ON `user`.group_id = `groups`.id
				GROUP BY `groups`.id)
				UNION
				(SELECT `groups`.id, `groups`.name, SUM(file_size) AS bytes
				FROM `myFile`
				INNER JOIN `user` ON `user`.id = `myFile`.owner
				INNER JOIN `groups` ON `user`.group_id = `groups`.id
				GROUP BY `groups`.id)
				UNION
				(SELECT `groups`.id, `groups`.name, SUM(filesize) AS bytes
				FROM `storage`
				INNER JOIN `user` ON `user`.id = `storage`.uploaded_by
				INNER JOIN `groups` ON `user`.group_id = `groups`.id
				GROUP BY `groups`.id)
				UNION
				(SELECT `groups`.id, `groups`.name, SUM(filesize) AS bytes
				FROM `student_submission`
				INNER JOIN `user` ON `user`.id = `student_submission`.owner
				INNER JOIN `groups` ON `user`.group_id = `groups`.id
				GROUP BY `groups`.id)
				UNION
				(SELECT `groups`.id, `groups`.name, SUM(filesize) AS bytes
				FROM `submission_return`
				INNER JOIN `user` ON `user`.id = `submission_return`.owner
				INNER JOIN `groups` ON `user`.group_id = `groups`.id
				GROUP BY `groups`.id)
			) allTotals ON `groups`.id = allTotals.id
			GROUP BY `groups`.name
			ORDER BY `groups`.id
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
	 * @return array
	 */
	public function findTypeUsage() {
		$query = "
			SELECT *
			FROM (
				(SELECT 'Resources' AS name, SUM(file_size) AS bytes
				FROM `file`
				WHERE deleted = 0)
				UNION
				(SELECT 'MyFiles' AS name, SUM(file_size) AS bytes
				FROM `myFile`
				WHERE deleted = 0)
				UNION
				(SELECT 'Storage' AS name, SUM(filesize) AS bytes
				FROM `storage`)
				UNION
				(SELECT 'Submissions' AS name, SUM(filesize) AS bytes
				FROM `student_submission`)
				UNION
				(SELECT 'Returns' AS name, SUM(filesize) AS bytes
				FROM `submission_return`)
			) AS allTotals
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		$result = array();

		while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$result[] = $row;
		}

		return $result;
	}

	/**
	 * Find total type usage
	 *
	 * @param $orderBy
	 * @param $order
	 * @return array
	 */
	public function findUserUsage($orderBy = false, $order = 'DESC') {
		$query = "
			SELECT `user`.firstname, `user`.lastname, COALESCE(SUM(bytes), 0) AS bytes
			FROM `user`
			INNER JOIN `groups` ON `user`.group_id = `groups`.id
			LEFT JOIN
			(
				(SELECT owner AS userId, SUM(file_size) AS bytes
				FROM `file`
				WHERE deleted = 0
				GROUP BY owner)
				UNION
				(SELECT owner AS userId, SUM(file_size) AS bytes
				FROM `myFile`
				WHERE deleted = 0
				GROUP BY owner)
				UNION
				(SELECT uploaded_by AS userId, SUM(filesize) AS bytes
				FROM `storage`
				GROUP BY uploaded_by)
				UNION
				(SELECT owner AS userId, SUM(filesize) AS bytes
				FROM `student_submission`
				GROUP BY owner)
				UNION
				(SELECT owner AS userId, SUM(filesize) AS bytes
				FROM `submission_return`
				GROUP BY owner)
			) allTotals ON `user`.id = allTotals.userId
			GROUP BY `user`.id
		";

		if ($orderBy) {
			$query .= "
				ORDER BY :orderBy :order
			";

			$statement = $this->database->prepare($query);
			$statement->execute(array(
				'orderBy' => $orderBy,
				'order' => $order
			));
		}
		else {
			$statement = $this->database->prepare($query);
			$statement->execute();
		}

		$result = array();

		while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$result[] = $row;
		}

		return $result;
	}

	/**
	 * Insert filesizes of all submissions
	 */
	public function updateSubmissions() {
		$dirhandle = opendir(\Cfg::get('submission_dir'));
		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();

		while ($filepointer = readdir($dirhandle)) {
			if (($filepointer != '.') && ($filepointer != '..')) {
				$size = filesize(\Cfg::get('submission_dir') . '/' . $filepointer);

				try {
					$file = $mapper->findById(str_replace('.dat', '', $filepointer));
					$file->setFilesize($size);
					$mapper->save($file);
				}
				catch(\Exception $e) {
				}
			}
		}
	}
}