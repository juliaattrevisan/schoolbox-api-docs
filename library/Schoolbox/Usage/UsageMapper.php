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
	 * The total number of users
	 *
	 * @return mixed
	 */
	public function numUsers() {
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
	 * Find total usage
	 *
	 * This summates the filesizes of all files in the specified location.
	 * This includes files which may have been marked as softdeleted in the db
	 */
	public function findTotalUsage() {
		$total = disk_total_space(\Cfg::get('file_dir'));
		$free = disk_free_space(\Cfg::get('file_dir'));
		$used = $total - $free;

		return array(
			'Used' => $used,
			'Available' => disk_total_space(\Cfg::get('file_dir')) - $used
		);
	}

	/**
	 * Find total category usage
	 *
	 * This does NOT summate files which have been marked as softdeleted in the db
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
	 * This summates the filesizes of all files in the specified location.
	 * This includes files which may have been marked as softdeleted in the db
	 *
	 * @return array
	 */
	public function findTypeUsage() {
		$cmd = 'du -sb ';

		/* "categories" of usage
		 */
		$collection = new \Schoolbox\Collection();
		$collection->add(new Item('Cache'));
		$collection->add(new Item('Dropbox'));
		$collection->add(new Item('My Files'));
		$collection->add(new Item('Resources'));
		$collection->add(new Item('Returns'));
		$collection->add(new Item('Storage'));
		$collection->add(new Item('Submissions'));
		$collection->add(new Item('Videos'));
		$collection->add(new Item('Other'));

		/* Map keys to locks
		 */
		$mapping = array(
			'dropbox_dir' => 'Dropbox',
			'file_dir' => 'Resources',
			'logs_dir' => 'Cache',
			'minify_dir' => 'Cache',
			'myfiles_cache_dir' => 'Cache',
			'myfile_dir' => 'My Files',
			'portrait_dir' => 'Cache',
			'returns_dir' => 'Returns',
			'storage_dir' => 'Storage',
			'stream_dir' => 'Cache',
			'submission_dir' => 'Submissions',
			'thumbnail_dir' => 'Cache',
			'video_dir' => 'Videos',
			'weather_dir' => 'Cache',
			'xml_dir' => 'Cache',
			'zip_dir' => 'Cache',
		);

		/* Quizzle it
		 */
		$query = "
			SELECT `name`,`value`
			FROM `config`
			WHERE `name` LIKE '%dir'
			AND `name` != 'template_dir'
			ORDER BY `name`
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		/* Iterate
		 */
		while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

			/* Gather results for directories which exist
			 */
			if (is_dir($row['value'])) {
				$path = escapeshellarg($row['value']);

				$output = shell_exec($cmd . $path);

				$bits = explode(' ', $output);

				/* Get to that Item object and byte it
				 *
				 * If there's a directory that isn't mapped, throw it into Other
				 *
				 * That is a good way to see that something is occupying space that we don't
				 * necessarily know about...like another _dir folder in config table
				 */
				if (array_key_exists($row['name'], $mapping)) {
					$key = $mapping[$row['name']];
				}
				else {
					$key = 'Other';
				}

				$collection->getBy('name', $key)->add($bits[0]);
			}
		}

		/* Big slices go first
		 */
		$collection->sortBy('bytes', 'asc');

		return $collection;
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
		$query = "
			SELECT
				`user`.firstname, `user`.lastname,
				CONCAT(`user`.firstname, ' ', `user`.lastname) AS fullname,
				`groups`.name,
				COALESCE(SUM(bytes), 0) AS bytes
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

		/* Select order by
		 */
		if (!in_array($orderBy, array('firstname', 'lastname', 'name', 'bytes'))) {
			$orderBy = 'lastname';
		}

		if (!in_array($order, array('DESC', 'ASC'))) {
			$order = 'ASC';
		}

		/* Cast Away
		 */
		$index = (int)$index;
		$usersPerPage = (int)$usersPerPage;

		/* Pagination
		 */
		$start = $index * $usersPerPage;

		$query .= "
			ORDER BY $orderBy $order
			LIMIT $start, $usersPerPage
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