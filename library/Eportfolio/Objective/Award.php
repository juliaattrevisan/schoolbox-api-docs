<?php
/**
 * Eportfolio_Objective_Award
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Objective_Award extends Eportfolio_Objective_Abstract implements Eportfolio_Objective_Interface {
	public $_table = 'eportfolio_objective_award';
	public $_index = 'id';

	/* This is the public-facing representation of the
	 * primary key used in the db
	 */
	protected $facadePrimaryKey = 'objectiveAwardID';

	/**
	 * Render a component on the page with appropriate tokens
	 *
	 * @param  $page
	 * @return void
	 */
	public function render() {
		$data = $this->getByField('component_location_id', $this->getComponentLocationId());

		$this->getPage()->setVar(array(
			'OBJECTIVE_AWARD_ID' => $data['id'],
			'OBJECTIVE_AWARD' => $this->listify($data['body']),
		));
	}

	/**
	 * Render modification screen
	 *
	 * @abstract
	 * @return void
	 */
	public function renderModify() {
		$data = $this->getByComponentLocationID($this->getComponentLocationId());

		$this->getPage()->setFile(TEMPLATE_content, 'eportfolio/components/objective/award/modify.html');

		$this->getPage()->setVar(array(
			'USER_ID' => $this->getUserId(),
			'COMPONENT_LOCATION_ID' => $this->getComponentLocationId(),
			'OBJECTIVE_AWARD_ID' => $data['id'],
			'OBJECTIVE_AWARD' => $data['body'],
		));
	}
}
