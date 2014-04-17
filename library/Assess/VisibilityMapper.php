<?php
/**
 * VisibilityMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Assess;


class VisibilityMapper extends \Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('assess_visibility');
		$this->hasModel('\\Assess\\Visibility');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
	}

	/**
	 * Get a list of visibility consts
	 *
	 * This can prevent Quiz marks from appearing in Report Cards, from being
	 * shown to the participant at the end, etc
	 *
	 * Note that the assess_visibility table has been made synonymous with the
	 * consts defined in SubmissionBox (zero-based) and thus won't autoincrement
	 *
	 * @param \Schoolbox\User $user
	 * @return array
	 */
	public function getVisibilityArray(\Schoolbox\User $user) {
		$groupHandler = new \Group();

		$visibilities = array();

		if ($groupHandler->isStaff()) {
			$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PRIVATE;
			$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_STUDENT;
			$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT;
		}
		else if ($groupHandler->isStudent()) {
			$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_STUDENT;
			$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT;
		}
		else if ($groupHandler->isParentOfChild($user->getId())) {
			$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT;
		}

		return $visibilities;
	}
} 