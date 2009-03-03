<?php

/* Template management for homepages */
require_once($_SERVER['DOCUMENT_ROOT'].'/../include/model.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/../library/homepage.php');

class HomepageTemplate extends  Model {

		function HomepageTemplate() {
			$this->_table = 'homepages';
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

		function createTemplate($cid, $template)
		{
		
			if( $template == 1)
				$this->createSubjectPage($cid);
			else if($template == 2)
				$this->createGroupPage($cid);
			else if($template == 3)
				$this->createGroupForumPage($cid);
			else if($template == 4)
				$this->createFacultyPage($cid);
			else if($template == 5)
				$this->createSubjectForumPage($cid);
			else if($template == 6)
				$this->createGroupForumPage();
		}

		// Default subject page
		function createSubjectPage($cid)
		{
			$homepage = new Homepage($cid);
			
			$homepage->savePageTemplate(1);
			$homepage->deleteComponentPositions();

			// Default components
			$homepage->saveComponentPosition(8, 1, 1, '');
			$homepage->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(12, 2, 1, serialize(array('size' => 7)));
			$homepage->saveComponentPosition(11, 2, 2, serialize(array('size' => 7)));
			$homepage->saveComponentPosition(9, 2, 3, '');
			$homepage->saveComponentPosition(10, 2, 4, '');

			// Default colors
			$homepage->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createSubjectForumPage($cid)
		{
			$homepage = new Homepage($cid);
			
			$homepage->savePageTemplate(1);
			$homepage->deleteComponentPositions();

			// Default components
			$homepage->saveComponentPosition(8, 1, 1, '');
			$homepage->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(12, 2, 1, serialize(array('size' => 7)));
			$homepage->saveComponentPosition(11, 2, 2, serialize(array('size' => 7)));
			$homepage->saveComponentPosition(5, 2, 3, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(9, 2, 3, '');
			$homepage->saveComponentPosition(10, 2, 4, '');

			// Default colors
			$homepage->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createGroupPage($cid)
		{
			$homepage = new Homepage($cid);
			
			$homepage->savePageTemplate(1);
			$homepage->deleteComponentPositions();

			// Default components
			$homepage->saveComponentPosition(8, 1, 1, '');
			$homepage->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(4, 2, 1, '');
			$homepage->saveComponentPosition(10, 2, 2, '');

			// Default colors
			$homepage->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createGroupForumPage($cid)
		{
			$homepage = new Homepage($cid);
			
			$homepage->savePageTemplate(1);
			$homepage->deleteComponentPositions();

			// Default components
			$homepage->saveComponentPosition(8, 1, 1, '');
			$homepage->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$homepage->saveComponentPosition(5, 2, 1, serialize(array('size' => 7)));
			$homepage->saveComponentPosition(4, 2, 2, '');
			$homepage->saveComponentPosition(10, 2, 3, '');
			
			// Default colors
			$homepage->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createFacultyPage($cid)
		{
			$homepage = new Homepage($cid);
			
			$homepage->savePageTemplate(1);
			$homepage->deleteComponentPositions();

			// Default components
			$homepage->saveComponentPosition(15, 1, 1, serialize(array('image' => 'Preview Image')));
			$homepage->saveComponentPosition(8, 1, 2, '');
			$homepage->saveComponentPosition(4, 2, 1, '');
			$homepage->saveComponentPosition(7, 2, 2, serialize(array('size' => 7)));
			$homepage->saveComponentPosition(6, 2, 3, serialize(array('size' => 7)));
			$homepage->saveComponentPosition(10, 2, 4, '');

			// Default colors
			$homepage->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}
}

?>
