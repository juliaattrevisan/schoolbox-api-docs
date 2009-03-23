<?php

/* Template management for homepages */
require_once($_SERVER['DOCUMENT_ROOT'].'/../include/model.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/../library/homepage.php');

class HomepageTemplate extends  Model {

		function HomepageTemplate() {
			$this->_table = 'homepageTemplate';
			$this->_index = 'id';
			
			parent::Model();
		}

		function getTemplateListDropDown() {
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

		function createTemplate($cid, $templateID)
		{
		
			$template = $this->getByID($templateID);
			
			$homepage = new Homepage($cid);
			$homepage->savePageTemplate($template['layout']);
			
			$homepage->deleteComponentPositions();
			
			$compPos = $this->getTemplatePositions($templateID);
			
			foreach($compPos as $comp) {
				$homepage->saveComponentPosition($comp['componentID'], $comp['column'], $comp['position'], $comp['config']);
			}
			
			$homepage->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}


		function getTemplatePositions($template) {
			$query = "SELECT componentID, `column`, `position`, config FROM homepageTemplateLayout 
						JOIN homepage_comp ON componentID=id WHERE templateID = $template ORDER BY `column`, `position`";
			return $this->db->getAll($query);
		}
}

?>
