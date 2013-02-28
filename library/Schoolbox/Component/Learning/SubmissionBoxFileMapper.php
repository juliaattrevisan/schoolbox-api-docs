<?php
/**
 * Mapper for the SubmissionBoxFile model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Component\Learning;
use Schoolbox;

class SubmissionBoxFileMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_box_file');
		$this->hasModel('\\Schoolbox\\Component\\Learning\\SubmissionBoxFile');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'storage_id',
			'property'   => 'storageId',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'box_id',
			'property'   => 'submissionBoxId',
			'type'       => self::TYPE_INTEGER,
		));
	}
}
