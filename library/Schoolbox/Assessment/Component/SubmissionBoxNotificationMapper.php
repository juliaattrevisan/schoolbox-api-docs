<?php
/**
 * Mapper for the SubmissionBoxFile model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;

class SubmissionBoxNotificationMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_notification');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBoxNotification');
		$this->hasMap(array(
			'column'     => 'box_id',
			'property'   => 'submissionBoxId',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'type',
			//
			// Actually an enum but we'll sort that later.
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'enabled',
			'property'   => 'enabled',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'sent',
			'property'   => 'sent',
			'type'       => self::TYPE_BOOLEAN,
		));
	}
}
