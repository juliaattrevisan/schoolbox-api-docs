<?php

require_once SITE_root . '/include/model.php';

define('FORUM_THREADS_PER_PAGE', 30);
define('FORUM_POSTS_PER_PAGE', 15);

class Forum extends Model {

	function Forum () {
	
		$this->_table = "forumThread";
		$this->_index = "threadID";
		$this->_postTable = "forumPost";
		
		parent::Model();
	}
	
	function getRecentThreadList($categoryID, $config) {
		$query = "SELECT t.*, u.title userTitle, u.firstname userFirstname, u.lastname userLastname
				  FROM forumThread t
				  LEFT JOIN user u ON u.id = t.userID 
				  WHERE t.categoryID = $categoryID 
				  AND t.deleted = 'false' 
				  ORDER BY t.date DESC ";
				  
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}";  
		
		return $this->db->getAll($query);
	}

	function getThreadList ($categoryID, $index = 0, $limit = 0) {
		$query = "SELECT t.*, u.title userTitle, u.firstname userFirstname, u.lastname userLastname, COUNT(DISTINCT p.postID) - 1 replies
				  FROM forumThread t
				  LEFT JOIN user u ON u.id = t.userID 
				  LEFT JOIN forumPost p ON p.threadID = t.threadID AND p.deleted = 'false'
				  WHERE t.categoryID = $categoryID 
				  AND t.deleted = 'false' 
				  GROUP BY p.threadID
				  ORDER BY t.date DESC ";
		if ($limit)
			$query .= "LIMIT $index, $limit ";
		
		return $this->db->getAll($query);
	}
	
	function getThread ($threadID) {
		$query = "SELECT t.*, p.postID, p.body FROM forumThread t, forumPost p 
				  WHERE t.threadID = p.threadID AND t.threadID = $threadID AND t.deleted = 'false'
				  ORDER BY p.date ASC LIMIT 1";
		return $this->db->getRow($query);			
	}
	
	function getPostList ($threadID, $index = 0, $limit = 0) {
		$query = "SELECT p.*, u.title userTitle, u.firstname userFirstname, u.lastname userLastname, g.name userGroup FROM forumPost p
				  LEFT JOIN user u ON u.id = p.userID
				  LEFT JOIN groups g ON g.id = u.group_id
				  WHERE p.threadID = $threadID AND p.deleted = 'false' ";
		if ($limit)
			$query .= "LIMIT $index, $limit ";
		
		return $this->db->getAll($query);
	}
	
	function getPost ($postID) {
		$query = "SELECT p.*, u.title userTitle, u.firstname userFirstname, u.lastname userLastname 
				  FROM ".$this->_postTable." p 
				  LEFT JOIN user u ON u.id = p.userID 
				  WHERE postID = $postID AND p.deleted = 'false'";
		return $this->db->getRow($query);
	}
	
	function hasForum($categoryID) {
		$query = "SELECT * FROM homepage_comp_location WHERE cid = $categoryID AND component = 5";
		$result = $this->db->getRow($query);
		if (isset($result['cid']))
			return true;
		else
			return false;
	}
	
	function lockThread ($threadID) {
		$data = array('threadID'	=> $threadID,
					  'locked'		=> 'true',
					  );
		$this->update($data);
	}
	
	function uniqueView ($threadID) {
		$threads_viewed = explode(",", $_COOKIE['threads_viewed']);
		if (!is_array($threads_viewed) || !in_array($threadID, $threads_viewed)) {
			$query = "UPDATE ".$this->_table." SET views = views + 1 WHERE threadID = $threadID";
			$this->db->query($query);
			
			setcookie('threads_viewed', $_COOKIE['threads_viewed'] . ',' . $threadID);
		}
		return;		
	}

	function getPageNumber($postID) {
		$query = "SELECT o.* FROM ".$this->_postTable." p 
				  LEFT JOIN ".$this->_postTable." o ON o.threadID = p.threadID AND o.deleted = 'false'
				  WHERE p.postID = $postID AND p.deleted = 'false'";
		$i = 0;
		$postNumber = 0;
		$postList = $this->db->getAll($query);
		if (is_array($postList)) {
			foreach ($postList as $post) {
				$i++;
				if ($post['postID'] == $postID) {
					$postNumber = $i;
					break;
				}
			}
		}
		
		return ceil($postNumber / FORUM_POSTS_PER_PAGE);
		
	}
	
	function parseBB ($text) {
		$find 	 = array('%\[quote\](.+)\[/quote\]%',
						 '%\[quote(="([^"]+)")?\](.+)\[/quote\]%'
						 );
		$replace = array('<blockquote><p>$1</p></blockquote>',
						 '<cite>$2</cite><blockquote><p>$3</p></blockquote>'
						 );

		return preg_replace($find, $replace, html_entity_decode($text));
		
	}
	
	function create ($data) {
		$data['date'] = 'NOW()';
		return parent::create($data);
	}
	
	function delete($threadID) {
		$data = array('threadID'	=> $threadID,
					  'deleted'		=> 'true',
					  );
		return $this->update($data);
	}
	
	function createPost ($data) {
		$data['date'] = 'NOW()';
		$this->db->insert($this->_postTable, $data);
		$data['postID'] = $this->db->insertID();
		
		
		/* update thread */
		require_once SITE_root . '/library/user.php';
		$userHandler = new User;
		$user = $userHandler->getByID($_SESSION['id']);
		
		$thread = array('threadID'			=> $data['threadID'],
						'last_post_id'		=> $data['postID'],
						'last_post_date'	=> 'NOW()',
						'last_post_user_id'	=> $_SESSION['id'],
						'last_post_user'	=> formatName($user),
						);
		$this->update($thread);
						
		return $data['postID'];
	}
	
	function updatePost ($data) {
		$data['modified'] = 'NOW()';
		
		unset($data['threadID']);
		unset($data['userID']);
		
		$current = $this->getPost($data['postID']);
		if ($current['userID'] != $_SESSION['id'])
			$data['censored'] = 'true';
		
		return $this->db->update($this->_postTable, 'postID', $data);
	}
	
	function deletePost ($postID) {
	
		$data = array('postID'		=> $postID,
					  'deleted'		=> 'true',
					  );
		return $this->updatePost($data);		
	}
	
	function homepageClean ($cid) {
		$query = "DELETE {$this->_table}, {$this->_postTable}
			FROM {$this->_table}, {$this->_postTable}
			WHERE {$this->_table}.{$this->_index} = {$this->_postTable}.{$this->_index}
				AND {$this->_table}.categoryID = $cid";
		
		return $this->db->query($query);
	}
}
?>