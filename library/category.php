<?php

require_once dirname(__FILE__) . '/../include/model.php';

class Category extends Model {

	function Category () {
	
		$this->_table = "category";
		$this->_index = "id";
		$this->categoryTree = array();
		
		parent::Model();
	}
	
	function getCategoryList ($categoryID, $config = false) {
		$query = "SELECT id, name, code, template FROM category WHERE parent = $categoryID ";
		
		$sortFields = array('name' => 'name', 'date' => 'id');
		$config['sort'] = in_array($config['sort'], $sortFields) ? $config['sort'] : 'name';
		$config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

		$query .= " ORDER BY " . $config['sort'] . " " . $config['sortdir'];
		
		if($config['size'] > 0) 
			$query .= " LIMIT 0, " . $config['size']; 
							  
		return $this->db->getAll($query);
	}
	
	function hasChildren($categoryID) {
		$query = "SELECT c.id as children 
				  FROM category c
				  WHERE parent = $categoryID
				  LIMIT 1,1";
		
		return $this->db->getOne($query);
	}

	function getParent($categoryID) {
		$query = "SELECT parent FROM category WHERE id = $categoryID";
		return $this->db->getOne($query);
	}
	
	function getByID($categoryID) {
		$query = "SELECT c.*, hc.*, u.id owner, u.title, u.firstname, u.lastname, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName, g.type type
				  FROM (category c)
				  LEFT JOIN homepage_config hc ON  hc.cid = c.id
				  LEFT JOIN userAccess a ON a.sid = c.id AND a.type = 3 AND service = " . HOMEPAGESERVICE . "
				  LEFT JOIN user u on u.id = a.uid 
				  LEFT JOIN userGroups g ON g.cid = c.id
				  WHERE c.id = '$categoryID'"; 
		return $this->db->getRow($query);		
	}
	
	function logPageView($categoryID) {
		$query = "SELECT datetime FROM homepage_log WHERE cid = '". $categoryID. "' AND user = '".$_SESSION['id']."'";
		$this->db->query($query);
		
		if($row = $this->db->getNextArray() ) {
			$query = "UPDATE homepage_log SET datetime = NOW(), hitcount = hitcount + 1 WHERE cid = '" .$categoryID ."'
			AND user = '".$_SESSION['id']."'";
		}
		else
		{
			$query = "INSERT INTO homepage_log (datetime, user, cid,hitcount) VALUES ( NOW() , 
			'".$_SESSION['id']."', '" . $categoryID . "', '0')";
		}
		
		$this->db->query($query);	
	}
	
	function lastVisit($categoryID, $userID) {
		//get the time since the last visit to this homepage
		$query = "SELECT DATE_FORMAT(`datetime`, '%a %D %b %H:%i') as visit FROM homepage_log, category
				  WHERE category.id = '$categoryID'
				  AND	category.id = homepage_log.cid
				  AND   homepage_log.user = '$userID'
		"; 
		return $this->db->getOne($query);
	}
	
	function getComponents($categoryID) {

		$query = "SELECT position, `column`, component, homepage_comp.config dfconfig, homepage_comp_location.config, name, method, filename 
				  FROM homepage_comp_location, homepage_comp 
				  WHERE component = id AND cid = '" . $categoryID . "'
				  ORDER BY position";
		return $this->db->getAll($query);
	
	}
	
	function getImages($categoryID) {
		$query = "SELECT id, file.description 
				  FROM file 
				  LEFT JOIN allowedFileTypes ON mime_type = mime 
				  WHERE category = '{$categoryID}' 
				  AND deleted = '0' AND INSTR(mime_type, 'image/') AND inline = 'true'
				  ORDER BY submitted";
		return $this->db->getAll($query);
	}


	function getCategoryTree($parent = 0, $indent = '') {

		$categoryList = $this->getCategoryList($parent);
		if (is_array($categoryList)) {
			foreach ($categoryList as $category) {

				$thisRow['CAT_ID'] = $category['id'];
				$thisRow['CAT_NAME'] = $indent . scrubTEXT($category['name']) . ' ' . scrubTEXT($category['code']);
				array_push($this->categoryTree, $thisRow);

				$this->getCategoryTree($category['id'], $indent . "&nbsp; &nbsp;|&nbsp;");
			}
		}
		return $this->categoryTree;
	}	

	function getTemplateList() {

		$output = array();
		$query = "SELECT id, name FROM homepages";
		$templateList =  $this->db->getAll($query);
		if (is_array($templateList)) {
			foreach ($templateList as $template) {
				$row['TEMPLATE_ID'] = $template['id'];
				$row['TEMPLATE_NAME'] = $template['name'];
				$output[] = $row;
			}
		}
		return $output;
	}

	function getGroupPerms($categoryID) {
		$query = "SELECT id, name, type FROM groups g 
				  LEFT JOIN perms p ON p.gid = g.id AND p.cid = '$categoryID'
				  ORDER BY id";
		return $this->db->getAll($query);
	}	



	function logAction ($categoryID, $action = 'modify') {
		$query = "INSERT INTO cmsLog SET object = 'folder', objectID = '$categoryID', action = '$action', user = '" . $_SESSION['id'] . "', time = NOW()";
		return $this->db->query($query);
	}

	function deletePerms($cid)
	{
		$this->db->dbDeleteRow('perms', 'cid', $cid);
	}

	function create($data) {
		$categoryID = parent::create($data);
		$this->logAction($categoryID, 'create');
		return $categoryID;
	}
	
	function update ($data) {
		parent::create($data);
		$this->logAction($data[$this->_index], 'modify');
	}
	
	function delete($categoryID) {
		parent::delete($categoryID);
		$this->logAction($categoryID, 'delete');
	}

}

?>