<?php

/* Template management for eportfolio */

class EportfolioTemplate extends  DeprecatedModel {

	public function __construct() {
		$this->_table = 'eportfolioTemplate';
		$this->_index = 'id';

		parent::__construct();
	}

	public function getTemplateListDropDown() {
		$templateList =  $this->getAll('name');
		if (is_array($templateList)) {
			foreach ($templateList as $template) {
				$row['TEMPLATE_ID'] = $template['id'];
				$row['TEMPLATE_NAME'] = $template['name'];
				$output[] = $row;
			}
		}
		return $output;
	}

	/**
	 * Create an eportfolio template
	 *
	 * @param $uid
	 * @param $templateID
	 */
	public function createTemplate($uid, $templateID) {
		if (is_numeric($templateID)) {
			$template = $this->getByID($templateID);

			$eportfolioPage = new Eportfolio($uid);
			$eportfolioPage->savePageTemplate($templateID);
			$eportfolioPage->updateColumnWidths($template['left_column_width'], $template['right_column_width']);
			$eportfolioPage->deleteComponentPositions();

			$compPos = $this->getTemplatePositions($templateID);

			foreach($compPos as $comp) {
				$eportfolioPage->saveComponentPosition($comp['componentID'], $comp['column'], $comp['position'], $comp['config']);
			}

			/* Create the default colours and set them
			 */
			$defaultColour = array();

			foreach(array('top','left','right') as $position) {
				$defaultColour = array_merge($defaultColour, array(
					$position . '_head_font' => Config::get('homepage_colour_head_font'),
					$position . '_head_bg' => Config::get('homepage_colour_head_bg'),
					$position . '_font' => Config::get('homepage_colour_font'),
					$position . '_bg_primary' => Config::get('homepage_colour_bg_primary')
				));
			}

			$eportfolioPage->saveDisplayProperties($defaultColour);
		}
	}

	public function getTemplatePositions($template) {
		$query = "
			SELECT componentID, `column`, `position`, config
			FROM eportfolioTemplateLayout
			JOIN eportfolio_comp ON componentID=id
			WHERE templateID = $template
			ORDER BY `column`, `position`
		";

		return $this->db->getAll($query);
	}
}

