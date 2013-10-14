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
			) AS allTotals
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		$result = $statement->fetch(\PDO::FETCH_ASSOC);

		return $result['total'];
	}
}