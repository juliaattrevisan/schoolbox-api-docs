<?php

require_once dirname(__FILE__) . '/../include/model.php';

class Category extends Model {

	function Category () {
	
		$this->_table = "category";
		$this->_index = "id";
		$this->categoryTree = array();
		$this->nodeList = array();
		
		parent::Model();
	}
	
	function getCategoryList ($categoryID, $config = false) {
		$query = "SELECT id, name, GROUP_CONCAT(co.code) code, cf.template, parent 
				  FROM category ca 
				  LEFT JOIN categoryCode co ON co.categoryID = ca.id
				  LEFT JOIN homepage_config cf ON cf.cid = ca.id
				  WHERE parent = $categoryID 
				  GROUP BY ca.id";
		
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
				  LIMIT 1";
		
		return $this->db->getOne($query);
	}

	function getParent($categoryID) {
		$query = "SELECT parent FROM category WHERE id = $categoryID";
		return $this->db->getOne($query);
	}
	
	function getByID($categoryID) {
		$query = "SELECT c.*, GROUP_CONCAT(co.code) code, u.id owner, u.title, u.firstname, u.lastname, g.type type, cf.template
				  FROM (category c)
				  LEFT JOIN categoryCode co ON co.categoryID = c.id
				  LEFT JOIN userAccess a ON a.sid = c.id AND a.type = 3 AND service = " . HOMEPAGESERVICE . "
				  LEFT JOIN user u on u.id = a.uid 
				  LEFT JOIN userGroups g ON g.cid = c.id
				  LEFT JOIN homepage_config cf ON cf.cid = c.id
				  WHERE c.id = '$categoryID' 
				  GROUP BY c.id"; 
		return $this->db->getRow($query);		
	}
	
	function getByCode($code) {
		$query = "SELECT categoryID FROM categoryCode WHERE code = ".$this->db->smartQuote($code)." ";
		$categoryID = $this->db->getOne($query);
		return $this->getByID($categoryID);
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
	
	function getImages($categoryID, $config = false) {
		$query = "SELECT id, file.description 
				  FROM file 
				  LEFT JOIN allowedFileTypes ON mime_type = mime 
				  WHERE category = '{$categoryID}' 
				  AND deleted = '0' AND INSTR(mime_type, 'image/') AND inline = 'true'
				  ORDER BY submitted";
				  
		if ($config['size'] == 0)
			$lim = "";
		else
			$lim = " LIMIT 1, ".IMAGESPERROW * $config['size'];
			
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

	function getCategoryNode($categoryID = 0) {
		$categoryID = (int)$categoryID;
		
		// get folder contents
		$categoryList = $this->getCategoryList($categoryID);
		
		if ($categoryID > 0) {
			// get parent folder contents
			$siblingList = $this->getCategoryNode($this->getParent($categoryID));
			
			if (is_array($categoryList)) {
				// find index of parent node in siblingList
				$total = count($siblingList);
				for ($i = 0; $i < $total; $i++)
					if ($siblingList[$i]['id'] == $categoryID) break;
					
				// insert folder contents next to parent node
				array_splice($siblingList, $i+1, 0, $categoryList);
			}
			return $siblingList;
		}
		else {
			return $categoryList;
		}
	}

	function getGroupPerms($categoryID) {
		$query = "SELECT id, name, type FROM groups g 
				  LEFT JOIN perms p ON p.gid = g.id AND p.cid = '$categoryID'
				  ORDER BY id";
		return $this->db->getAll($query);
	}	

	function checkForChildren($id)
	{
		//check for child folders
		$query = "SELECT id FROM category WHERE parent = '".$id."' LIMIT 1";
		if( $this->db->getOne($query) )
			return true;

		
		//check for files/links in category
		$query = "SELECT id FROM link WHERE category = '".$id."' LIMIT 1";
		if( $this->db->getOne($query) )
			return true;
		
		$query = "SELECT id FROM file WHERE category = '".$id."' AND deleted = '0' LIMIT 1";
		if( $this->db->getOne($query) )
			return true;
	
		return false;
	}


	function logAction ($categoryID, $action) {
		$logEntry = array(	'object'	=> 'folder',
							'objectId'	=> $categoryID,
							'action'	=> $action,
							'user'		=> $_SESSION['id'],
							);
		
		$this->db->insert('cmsLog', $logEntry);
	}
	
	function deletePerms($cid) {
		$this->db->delete('perms', 'cid', $cid);
	}

	function insertPerm($cid, $group, $access) {
		$perm = array(	'cid'		=> $cid,
						'gid'		=> $group,
						'type' 	=> $access,
					);
		$this->db->insert('perms', $perm); 
	}

	function create($data) {
			
		$group = $data['group'];
		unset($data['group']);
		
		$categoryID = parent::create($data);
		
		/* Setup group permissions */
		if(is_array($group)) {
			foreach($group as $groupID => $perm) {
				$this->insertPerm($categoryID, $groupID, $perm);
			}
		}
		
		/* Setup homepage template */
		if($data['template']) {
			require_once dirname(__FILE__) . '/homepage-template.php';
			$templateHandler = new HomepageTemplate;
			$templateHandler->createTemplate($categoryID, $data['template']);
		}
		else
			unset($data['template']);
		
		/* Insert synergetic subject code */
		$codeList = explode(",", $data['code']);
		if (is_array($codeList)) {
			$codeList = array_map('trim',$codeList);
			$codeList = array_unique($codeList);
			foreach ($codeList as $code) {
				if($code)
					$this->db->insert('categoryCode', array('categoryID' => $categoryID, 'code' => $code));
			}
		}
		
		$this->logAction($categoryID, 'create');
		return $categoryID;
	}
	
	function update ($data) {
		
		/* Redo group permissions */
		$this->deletePerms($data['id']);
		foreach($data['group'] as $groupID => $perm) {
			$this->insertPerm($data['id'], $groupID, $perm);
		}
		unset($data['group']);
	
		/* Setup homepage template */
		if($data['template']) {
			require_once dirname(__FILE__) . '/homepage-template.php';
			$templateHandler = new HomepageTemplate;
			$templateHandler->createTemplate($data['id'], $data['template']);
		}
		else
			unset($data['template']);
		
		/* Insert synergetic subject code */
		$this->db->delete('categoryCode', 'categoryID', $data['id']);
		$codeList = explode(",", $data['code']);
		unset($data['code']);
		if (is_array($codeList)) {
			$codeList = array_map('trim',$codeList);
			$codeList = array_unique($codeList);
			foreach ($codeList as $code) {
				if($code) 
					$this->db->insert('categoryCode', array('categoryID' => $data['id'], 'code' => $code));
			}
		}
		
		parent::update($data);
		
		$this->logAction($data[$this->_index], 'modify');
	}
	
	function delete($categoryID) {
		
		/* Check its empty before deleting */
		if($this->checkForChildren($categoryID) )
			error(12); 
		
		$this->deletePerms($categoryID);
		parent::delete($categoryID);
		
		require_once dirname(__FILE__) . '/homepage.php';
		$homepageHandler = new Homepage($categoryID);
		$homepageHandler->deleteHomepage();
		
		/* Delete group */
		require_once dirname(__FILE__) . '/group.php';
		$groupHandler = new Group();
		$groupHandler->disbandGroup($categoryID);
		
		$this->logAction($categoryID, 'delete');
	}

	function recursiveDelete($categoryID) {

		require_once dirname(__FILE__) . '/file.php';
		require_once dirname(__FILE__) . '/link.php';
		require_once dirname(__FILE__) . '/group.php';
		
		$groupHandler = new Group;
		
		if ($groupHandler->getPermission($categoryID) < WRITE_ACCESS)
			error(6);
		
		$childList = $this->getCategoryList($categoryID);
		
		if(is_array($childList)) {
			/* Recurse into child folders */		
			foreach($childList as $child) {
				$this->recursiveDelete($child['id']);		
			}
		}

		/* Delete all files */
		$fileHandler = new File;
		$fileList = $fileHandler->getAllInCategory($categoryID);
		if(is_array($fileList)) {
			foreach($fileList as $file) {
				$fileHandler->delete($file['id']);
			}
		}
		
		/* Delete all links */
		$linkHandler = new Link;
		$linkList = $linkHandler->getAllInCategory($categoryID);
		if(is_array($linkList)) {
			foreach($linkList as $link) {
				$linkHandler->delete($link['id']);
			}
		}
		
		/* Delete folder */
		$this->delete($categoryID);
	
		
	
	}
}

?>