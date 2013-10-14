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
			SELECT category.name, SUM(file_size) AS bytes
			FROM `file`
			INNER JOIN `user` ON file.owner = `user`.id
			LEFT JOIN category ON category.`id` = `file`.`category`
			WHERE deleted = 0
			GROUP BY category
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