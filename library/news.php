<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

class News extends Model {

	function News () {
	
		$this->_table = "news_items";
		$this->_index = "item_id";
		
		parent::Model();
	}

	function getByID($newsID) {
		
		$query = "SELECT * FROM ".$this->_table." WHERE ".$this->_index." = $newsID AND item_deleted = 0";
		$news = $this->db->getRow($query);
		
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
		$groupHandler = new Group(NEWS_SERVICE);
		
		$permLevel = $groupHandler->getPermission(0);
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
		$news['item_datetime_modified'] = 'NOW()';
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
		
		$news['item_datetime_modified'] = 'NOW()';
		
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
		else if(is_numeric($campusList))
			$this->db->insert('newsCampus', array('id' => $news['item_id'], 'campus' => $campusList));
		else
			$this->db->insert('newsCampus', array('id' => $news['item_id'], 'campus' => 1));	
	}
	
	function delete($newsID) {
		$newsID = (int)$newsID;

		if (!$this->hasWrite($newsID))
			return;
					
		$query = "UPDATE " . $this->_table . " SET item_deleted = 1 WHERE " . $this->_index . " = $newsID";
		return $this->db->query($query);
	}
	
	function getHeadlines() {
		$query = "
		SELECT `".$this->_index."`, item_heading, item_datetime_posted, item_datetime_modified, newsTopic.name topicName
		FROM `".$this->_table."` 
			JOIN newsCampus ON newsCampus.id = news_items.item_id 
				AND newsCampus.campus IN(".implode(',',$_SESSION['campus']).") 
			JOIN newsGroup ON newsGroup.id = news_items.item_id 
				AND newsGroup.gid = '".$_SESSION['gid']."'
			JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
		WHERE news_items.item_owner != '".$_SESSION['id']."' 
			AND item_datetime_expires > NOW() 
			AND item_deleted = 0 
		GROUP BY `".$this->_index."`
		ORDER BY item_datetime_modified DESC
		";
		
		$shareResults = $this->db->getAll($query);
		
		$query = "
		SELECT `".$this->_index."`, item_heading, item_datetime_posted, item_datetime_modified, newsTopic.name topicName
		FROM `".$this->_table."` 
		JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
		WHERE item_owner = '".$_SESSION['id']."' 
			AND item_datetime_expires > NOW() 
			AND item_deleted = 0 
		GROUP BY `".$this->_index."`
		ORDER BY item_datetime_modified DESC
		";
		
		$userResults = $this->db->getAll($query);
		
		//merge and sort the headlines based on time
		if(is_array($shareResults) && is_array($userResults))
			$headlines = array_merge($shareResults, $userResults);
		else if(is_array($shareResults))
			$headlines = $shareResults;
		else if(is_array($userResults))
			$headlines = $userResults;
		else
			return false;
				
		usort($headlines,array($this,"timecmp"));
		
		return $headlines;
	}
	
	function getNews($id = 0, $archive = false, $index = 0, $limit = 0) {
		$query = "
		SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_owner, news_items.item_body,
			DATE_FORMAT(news_items.item_datetime_posted, '%W %M %e @ %l:%i%p') AS post_datetime, news_items.item_datetime_posted, news_items.item_datetime_modified,
			DATE_FORMAT(news_items.item_datetime_expires, '%W %M %e @ %l:%i%p') AS expire_datetime,
			user.lastname, user.title, user.id as uid, user.firstname, newsTopic.name topicName
		FROM `".$this->_table."` 
		JOIN user ON news_items.item_owner = user.id
		JOIN newsCampus ON newsCampus.id = news_items.item_id 
			AND newsCampus.campus IN(".implode(',',$_SESSION['campus']).") 
			OR news_items.item_campus='4' 
		JOIN newsGroup ON newsGroup.id = news_items.item_id 
			AND newsGroup.gid = '".$_SESSION['gid']."'
		JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
		WHERE news_items.item_owner != '".$_SESSION['id']."' AND news_items.item_deleted = 0";

		if($id)
			$query .=" AND news_items.item_id = '$id'";
		else if($archive)
			$query .=" AND news_items.item_datetime_expires <= NOW() ";
		else
			$query .=" AND news_items.item_datetime_expires > NOW() ";
				

		$query .= " GROUP BY news_items.item_id ORDER BY newsTopic.name, news_items.item_datetime_modified DESC";

		$shareResults = $this->db->getAll($query);
		
		$query = "
		SELECT news_items.item_id, news_items.item_heading, news_items.item_owner, news_items.item_body, 
			DATE_FORMAT(news_items.item_datetime_posted, '%W %M %e @ %l:%i%p') AS post_datetime, news_items.item_datetime_posted, news_items.item_datetime_modified,
			DATE_FORMAT(news_items.item_datetime_expires, '%W %M %e @ %l:%i%p') AS expire_datetime,
			user.lastname, user.title, user.firstname, user.id as uid, newsTopic.name topicName
		FROM `".$this->_table."`
		JOIN user ON news_items.item_owner = user.id
		JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
		WHERE news_items.item_owner = '".$_SESSION['id']."'
			AND news_items.item_deleted = 0 
		"; 
		
		if($id)
			$query .=" AND news_items.item_id = '$id'";
		else if($archive)
			$query .=" AND news_items.item_datetime_expires <= NOW() ";
		else
			$query .=" AND news_items.item_datetime_expires > NOW() ";
			
		$query .= " ORDER BY newsTopic.name, news_items.item_datetime_modified DESC;";

		$userResults = $this->db->getAll($query);
	
		//merge and sort the headlines based on time
		if(is_array($shareResults) && is_array($userResults))
			$allNews = array_merge($shareResults, $userResults);
		else if(is_array($shareResults))
			$allNews = $shareResults;
		else if(is_array($userResults))
			$allNews = $userResults;
		else
			return false;
	
		usort($allNews,array($this,"timecmp"));
		
		if ($limit > 0)
			$allNews = array_slice($allNews, $index, $limit);
		
		return $allNews;
	}

	function getDailyNotice() {
		$query = "SELECT item_heading, item_body FROM news_items WHERE item_heading LIKE '%Daily Notices%' ORDER BY item_datetime_posted DESC LIMIT 1";
		return $this->db->getRow($query);
	}

	function timecmp($a, $b)
	{
	
		if (($topicResult = strcmp($a['topicName'], $b['topicName'])) == 0) {
			//just a quick function to enable sorting of news based on time
			if ($a['item_datetime_modified'] == $b['item_datetime_modified']) {
				return 0;
			}
			return ($a['item_datetime_modified'] > $b['item_datetime_modified']) ? -1 : 1;
		} else {
			return $topicResult;
		}
	}
	
	function getTopicByID($newsTopicID) {
		$query = "SELECT * FROM `newsTopic` WHERE `newsTopicID` = $newsTopicID";
		return $this->db->getRow($query);
	}
	
	function getTopicList() {
		$query = "SELECT * FROM `newsTopic` ORDER BY `name`";
		return $this->db->getAll($query);
	}
}

?>