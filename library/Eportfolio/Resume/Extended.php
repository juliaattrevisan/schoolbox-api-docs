<?php
/**
 * Eportfolio_Resume_Extended
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Resume_Extended extends Eportfolio_Resume_Abstract {
	protected $template = 'eportfolio/components/resume/extended.html';

	/* Objective Components to be used in this resume
	 */
	protected $allComponents = array(
		array(
			'method' => 'outputObjectiveCoverLetter',
			'block' => 'cover_letter',
		),
		array(
			'method' => 'outputObjectiveGoal',
			'block' => 'goal',
		),
		array(
			'method' => 'outputObjectiveEmploymentHistory',
			'block' => 'employment_history',
		),
		array(
			'method' => 'outputObjectiveEducationHistory',
			'block' => 'education_history',
		),
		array(
			'method' => 'outputObjectiveAward',
			'block' => 'award',
		),
		array(
			'method' => 'outputObjectiveSkill',
			'block' => 'skill',
		),
		array(
			'method' => 'outputObjectiveInterest',
			'block' => 'interest',
		),
	);
}
