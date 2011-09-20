<?php
/**
 * Eportfolio_Objective_EducationHistory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Objective_EducationHistory extends Eportfolio_Objective_Abstract implements Eportfolio_Objective_Interface {
	public $_table = 'eportfolio_objective_educationhistory';
	public $_index = 'id';

	/* This is the public-facing representation of the
	 * primary key used in the db
	 */
	protected $facadePrimaryKey = 'objectiveEducationHistoryID';

	/**
	 * Render a component on the page with appropriate tokens
	 *
	 * @param  $page
	 * @return void
	 */
	public function render() {
		$data = $this->getByField('component_location_id', $this->getComponentLocationId());

		$this->getPage()->setVar(array(
			'OBJECTIVE_EDUCATIONTHISTORY_ID' => $data['id'],
			'OBJECTIVE_EDUCATIONHISTORY' => $this->listify($data['body']),
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

		$this->getPage()->setFile(TEMPLATE_content, 'eportfolio/components/objective/educationhistory/modify.html');

		$this->getPage()->setVar(array(
			'USER_ID' => $this->getUserId(),
			'COMPONENT_LOCATION_ID' => $this->getComponentLocationId(),
			'OBJECTIVE_EDUCATIONHISTORY_ID' => $data['id'],
			'OBJECTIVE_EDUCATIONHISTORY' => $data['body'],
		));
	}
}
