<?php
/**
 * Eportfolio_Objective_Goal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Objective_Goal extends Eportfolio_Objective_Abstract implements Eportfolio_Objective_Interface {
	public $_table = 'eportfolio_objective_goal';
	public $_index = 'id';

	/* This is the public-facing representation of the
	 * primary key used in the db
	 */
	protected $facadePrimaryKey = 'objectiveGoalID';

	/**
	 * Special formatting
	 *
	 * @param  $body
	 * @return string
	 */
	public function getBody($body) {
		return $this->listify($body);
	}

	/**
	 * Render a component on the page with appropriate tokens
	 *
	 * @param  $page
	 * @return void
	 */
	public function render() {
		$data = $this->getByField('user_id', $this->getUserId());

		$this->getPage()->setVar(array(
			'OBJECTIVE_GOAL_ID' => $data['id'],
			'OBJECTIVE_GOAL' => $this->getBody($data['body']),
		));
	}

	/**
	 * Render modification screen
	 *
	 * @abstract
	 * @return void
	 */
	public function renderModify() {
		$data = $this->getForUser($this->getUserId());

		$this->getPage()->setFile(TEMPLATE_content, 'eportfolio/components/objective/goal/modify.html');

		$this->getPage()->setVar(array(
			'USER_ID' => $this->getUserId(),
			'COMPONENT_LOCATION_ID' => $this->getComponentLocationId(),
			'OBJECTIVE_GOAL_ID' => $data['id'],
			'OBJECTIVE_GOAL' => $data['body'],
		));
	}
}
