<?php

class Poll extends DeprecatedModel {

	function Poll () {
	
		$this->_table = "poll";
		$this->_index = "pollID";
		
		parent::DeprecatedModel();
	}

	function getByID($pollID) {
		$query = "SELECT *, UNIX_TIMESTAMP(close) close FROM " . $this->_table. " WHERE " . $this->_index . " = '$pollID'";
		return $this->db->getRow($query);
	}
	
	function getCurrentPoll ($categoryID) {
		$pollList = $this->getAllByCategory($categoryID);
		return $pollList[0];
	}

	function getAllByCategory ($categoryID) {
		$query = "SELECT * FROM poll WHERE categoryID = $categoryID ORDER BY created DESC";
		return $this->db->getAll($query);
	}

	function getData($pollID) {
		$poll = $this->getByID($pollID);
		if (!$poll['pollID'])
			return false;
		$data = array();
		$total = 0;
		for ($i = 0; $i < 10; $i++) {
			if (!empty($poll['option_' . $i])) {
				$set['option']	= $poll['option_' . $i];
				$set['result']	= $poll['result_' . $i];
				$data[] = $set;
				$total += $set['result'];
			}
		}
		
		$count = count($data);
		for ($i = 0; $i < $count; $i++) {
			$data[$i]['percentage'] = $total ? number_format(($data[$i]['result'] / $total) * 100, 2) : 0;
		}
		
		return $data;
	}

	function hasVoted ($pollID) {
		$poll = $this->getById($pollID);
		$cutoff = "";
		switch ($poll['frequency']) {
			case "hourly":
				$cutoff = "AND UNIX_TIMESTAMP(created) > " . (time() - 3600);
				break;
			case "daily":
				$cutoff = "AND UNIX_TIMESTAMP(created) > " . (time() - 86400);
				break;
		}

		$query = "SELECT count(*) FROM pollLog WHERE pollID = $pollID $cutoff AND userID = " . $_SESSION['id'];
		$vote = $this->db->getOne($query);

		if ($vote > 0)
			return true;
		return false;
	}
	
	function open($pollID) {
		$query = "SELECT count(*) FROM poll WHERE pollID = $pollID AND close > NOW()";
		$vote = $this->db->getOne($query);
		if ($vote > 0)
			return true;
		return false;		
	}
	
	function placeVote ($pollID, $vote) {
		if (is_numeric($pollID) && is_numeric($vote) && $vote >= 0 && $vote < 10 && !$this->hasVoted($pollID)) {
			$query = "UPDATE poll SET result_" . $vote . " = result_" . $vote . " + 1 WHERE pollID = $pollID";
			$this->db->query($query);
			$query = "INSERT INTO pollLog SET pollID = $pollID, userID = " . $_SESSION['id']. ", created = NOW()";
			$this->db->query($query);
			return true;
		}
		return false;
	}
	
	function hasResults($pollID) {
		$poll = $this->getByID($pollID);
		
		$total = 0;
		for ($i = 0; $i < 10; $i++) {
			$total += $poll["result_{$i}"];
		}
		
		return ($total > 0);
	}
	
	function homepageClean($cid) {
		$query = "DELETE p, l FROM  `".$this->_table."` p LEFT JOIN pollLog l ON l.pollID = p.pollID WHERE p.categoryID='$cid'";
		$this->db->query($query);
	}
}

?>