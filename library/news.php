<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/permission.php';

class News extends Model {

	function News () {
	
		$this->_table = "news_items";
		$this->_index = "item_id";
		
		parent::Model();
	}

	function getByID($newsID) {
		$news = parent::getByID($newsID);
		if ($news[$this->_index]) {
			$query = "SELECT * FROM newsGroup WHERE id = " . $news[$this->_index];
			$groupList = $this->db->getAll($query);
			if (is_array($groupList)) {
				foreach ($groupList as $group) {
					$news['groupList'][] = $group['gid'];
				}
			}

			$query = "SELECT * FROM newsCampus WHERE id = " . $news[$this->_index];
			$campusList = $this->db->getAll($query);
			if (is_array($campusList)) {
				foreach ($campusList as $campus) {
					$news['campusList'][] = $campus['campus'];
				}
			}			
		}
		return $news;
	}

	function getGroupList() {
		$query = "SELECT id, name FROM groups";
		$groupList = $this->db->getAll($query);
		if (is_array($groupList)) {
			$i = 0;
			foreach ($groupList as $group) {
				$output[$i]['GROUP_ID'] = $group['id'];
				$output[$i++]['GROUP_NAME'] = $group['name'];
			}
		}
		return $output;
	}
	
	function getCampusList() {
		$query = "SELECT id, name FROM luCampus WHERE id != ".ALLCAMPUS;
		$campusList = $this->db->getAll($query);
		if (is_array($campusList)) {
			$i = 0;
			foreach ($campusList as $campus) {
				$output[$i]['CAMPUS_ID'] = $campus['id'];
				$output[$i++]['CAMPUS_NAME'] = $campus['name'];
			}
		}
		return $output;
	}
	
	function hasWrite($newsID) {
		$permLevel = perm(0,3);
		$news = $this->getById($newsID);
		if (($permLevel >= 2 && $news['item_owner'] == $_SESSION['id']) || $permLevel >= 3)
			return true;
		return false;
	}
		
	function create($news) {
		$groupList = $news['groupList'];
		$campusList = $news['campusList'];
		
		unset($news['groupList']);
		unset($news['campusList']);
		
		$news['item_datetime_posted'] = 'NOW()';
		$news['item_owner'] = $_SESSION['id'];
		
		$newsID = parent::create($news);
		
		if (is_array($groupList)) {
			foreach ($groupList as $group) {
				$this->db->insert('newsGroup', array('id' => $newsID, 'gid' => $group));
			}
		}
		else if(is_numeric($groupList))
			$this->db->insert('newsGroup', array('id' => $newsID, 'gid' => $groupList));
		
		if (is_array($campusList)) {
			foreach ($campusList as $campus) {
				$this->db->insert('newsCampus', array('id' => $newsID, 'campus' => $campus));
			}
		}
		else if(is_numeric($campusList))
			$this->db->insert('newsCampus', array('id' => $newsID, 'campus' => $campusList));
		else
			$this->db->insert('newsCampus', array('id' => $newsID, 'campus' => 1));
		
		return $newsID;
	}
	
	function update($news) {

		if (!$this->hasWrite($news['item_id']))
			return;
	
		$groupList = $news['groupList'];
		$campusList = $news['campusList'];
		
		unset($news['groupList']);
		unset($news['campusList']);	

		parent::update($news);
		
		$this->db->delete('newsGroup', 'id', $news['item_id']);
		$this->db->delete('newsCampus', 'id', $news['item_id']);

		if (is_array($groupList)) {
			foreach ($groupList as $group) {
				$this->db->insert('newsGroup', array('id' => $news['item_id'], 'gid' => $group));
			}
		}
		if (is_array($campusList)) {
			foreach ($campusList as $campus) {
				$this->db->insert('newsCampus', array('id' => $news['item_id'], 'campus' => $campus));
			}
		}		
	}
	
	function delete($newsID) {
		$newsID = (int)$newsID;

		if (!$this->hasWrite($newsID))
			return;
					
		$query = "UPDATE " . $this->_table . " SET item_deleted = 1 WHERE " . $this->_index . " = $newsID";
		return $this->db->query($query);
	}
}

?>