<?php
/**
 * A DAV collection representing a single subject that a user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class DAV_SubjectServer_Subject extends Sabre_DAV_SimpleCollection
{
	/**
	 * Retrieves the collection of assessments for this subject.
	 *
	 * @return array
	 */
	public function getChildren() {
		return array();
	}
}
