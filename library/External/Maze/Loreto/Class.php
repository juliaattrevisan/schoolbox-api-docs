<?php
/**
 * External_Maze_Loreto_Class
 *
 * Loreto-specific customisation of Maze
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
*/

class External_Maze_Loreto_Class extends External_Maze_Class {

	/**
	 * @inheritdoc
	 */
	public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
		$ttPeriod = $this->getHelper()->getCurrentTTPERIOD();

		/* Assume Schoolbox $classCodes are in the format: '07SCI-1'
		 * where the 1 represents the Class which could be
		 * class 1, 2, 3 etc. of a particular subject
		 */
		$ingredients = $this->getHelper()->degenerateClassCode($classCode);

		$query = "
			SELECT
				T1TEACH,
				T2TEACH,
				'' AS T3TEACH
			FROM TT
			LEFT JOIN TTTG ON TT.TTKEY = TTTG.GKEY
			WHERE TT.TTPERIOD = '{$ttPeriod}' AND ";

		/* Accommodate multiple classcodes specifically
		 */
		$additional = [];

		for ($i = 0; $i < count($ingredients['ClassCode']); $i++) {
			$additional[] = "(TTTG.SUBJ = {$ingredients['ClassCode'][$i]} AND CLASS = {$ingredients['ClassNumber'][$i]})";
		}

		$query .= '(' . implode(' OR ', $additional) . ')';

		$part1 = $this->getDb()->getAll($query);
		$allClassCodes = implode(',', $ingredients['ClassCode']);

		if (!$part1) {
			$part1 = array();
		}

		$query = "
			SELECT
				TEACHER AS T1TEACH,
				TEACHERB AS T2TEACH,
				TEACHERC AS T3TEACH
			FROM KGC
			WHERE KGC.ROLL_GROUP IN ({$allClassCodes})
		";

		$part2 = $this->getDb()->getAll($query);

		if (!$part2) {
			$part2 = array();
		}

		$allTeachers = array_merge($part1, $part2);

		/* Uniqueify everything
		 */
		$teacherKeys = array();

		if (is_array($allTeachers) && count($allTeachers)) {
			foreach($allTeachers as $currentTeacher) {
				$teacherKeys[$currentTeacher['T1TEACH']] = '';
				$teacherKeys[$currentTeacher['T2TEACH']] = '';
				$teacherKeys[$currentTeacher['T3TEACH']] = '';
			}
		}

		/* Filter out empty values
		 */
		$teacherKeys = array_filter(array_keys($teacherKeys));

		/* A list of ids
		 */
		return $teacherKeys;
	}
}
