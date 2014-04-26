<?php
/**
 * Widgets
 *
 * A place to put shared floating functions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;


class Widgets {

	/**
	 * Outputs the visibility dropdown for this page.
	 *
	 * @param \SitePage $page
	 * @param $value The value of the dropdown to show.
	 */
	public function outputVisibilityDropdown(\SitePage $page, $value) {
		$options = array(
			array(
				'id'   => \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PRIVATE,
				'name' => 'Staff Only',
			),
			array(
				'id'   => \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_STUDENT,
				'name' => 'Staff and Students',
			),
			array(
				'id'   => \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,
				'name' => 'Staff, Students and their Parents',
			),
		);

		/* Remove elements from list based on max setting
		   Note this cascades popping elements as it goes, so case 0 pops 2, case 1 pops 1, case 3 pops 0*/
		switch(\Cfg::get('submission_visibility_max')) {
			case '0' :
				array_pop($options);

			case '1' :
				array_pop($options);

		}

		$page->dropDown(
			'mark_visibility_drop',
			$options,
			$value,
			'marktype',
			array(
				'name' => 'MARK_VISIBILITY_TEXT',
				'id'   => 'MARK_VISIBILITY_VALUE'
			)
		);
	}
}