<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2009 Lektec Consulting                *
*                                                           *														   
************************************************************/

require_once($_SERVER['DOCUMENT_ROOT'] .'/conf/conf.php');
require_once($_SERVER['DOCUMENT_ROOT'] .'/../include/db.php');

class Feed {

	function Feed() {
		$this->db = new dbManager(SQL_db);
	}

	function getFeed($cid, $limit) {
	
		$files = $this->getFiles($cid, $limit);
		$links = $this->getLinks($cid, $limit);
		$folders = $this->getFolders($cid, $limit);
		is_array($files)?0:$files=array();
		is_array($links)?0:$links=array();
		is_array($folders)?0:$folders=array();
		
		//get each of the areas
		$feedList = array_merge($files, $links, $folders);
		
		
		//sort them
		usort($feedList,array($this,"timecmp"));
		
		//cut off over limit
		$feedList = array_slice($feedList, 0, $limit);
	
		return $feedList;
	}

	function getFiles($cid, $limit) {
		$query = "SELECT f.id, u.firstname, u.lastname, u.title, u.id as uid, UNIX_TIMESTAMP(f.submitted) submitted, f.description as name, realname,
						af.description as type, inline
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE f.category = $cid AND f.hidden = 0 AND f.deleted = 0 
				  ORDER BY f.submitted DESC LIMIT 0, $limit";
		
		$this->db->query($query);
		while($row = $this->db->getNextArray()){
			
			if($row['realname'] != '') {
				if($row['inline'] == 'true')
					$url = "<a href=\"/sendframe.php?id=".$row['id']."\" target=\"_blank\">".scrubTEXT($row['name'])."</a>";
				else
					$url = '<a href="/send.php?id='.$row['id'].'">'.scrubTEXT($row['name']).'</a>';
			} else
				$url = scrubTEXT($row['name']);
			
			
			$file[] = array('date'		=> $row['submitted'],
							'type'		=> 'file',
							'message'	=> formatName($row) . ' uploaded the file '.scrubTEXT($row['name']),
							'messageHTML'	=> '<a href="/search/user/'.$row['uid'].'">' . formatName($row) . '</a> uploaded the file '.$url,
							);
		
		}
		return $file;
	}

	function getLinks($cid, $limit) {
		$query = "SELECT l.id, u.firstname, u.lastname, u.title, u.id as uid, UNIX_TIMESTAMP(l.submitted) submitted, description as name, url
				  FROM link l 
				  LEFT JOIN user u ON u.id = l.owner
				  WHERE category = $cid AND l.hidden = 0 
				  ORDER BY l.submitted DESC LIMIT 0, $limit";
		
		$this->db->query($query);
		while($row = $this->db->getNextArray()){
			
			$link[] = array('date'			=> $row['submitted'],
							'type'			=> 'link',
							'message'		=> formatName($row).' submitted the link '.scrubTEXT($row['name']),
							'messageHTML'	=> '<a href="/search/user/'.$row['uid'].'">'.formatName($row).'</a> submitted the link <a href="'.$row['url'].'">'.scrubTEXT($row['name']).'</a>',
							);
		
		}
		return $link;
	}
	
	function getNews($cid, $limit) {
		
	}
	
	function getFolders($cid, $limit) {
		$query = "SELECT c.id, name, UNIX_TIMESTAMP(time) submitted, action, u.firstname, u.lastname, u.title, u.id as uid
					FROM cmsLog JOIN category c ON objectId = c.id JOIN user u ON user = u.id WHERE objectId = $cid AND object = 'folder' AND action = 'created'
					ORDER BY time DESC LIMIT 0, $limit";
	
		$this->db->query($query);
		while($row = $this->db->getNextArray()){
			
			$folder[] = array('date'		=> $row['submitted'],
							'type'			=> 'folder',
							'message'		=>formatName($row) . ' created the folder '.scrubTEXT($row['name']),
							'messageHTML'	=> formatName($row) . ' created the folder <a href="/resources/'.$row['id'].'">' . scrubTEXT($row['name']) . '</a>',
							);
		
		}
		return $folder;
	}
	
	function getForum($limit) {
	
	}
	
	function getPoll($limit) {
	
	}
	
	// TODO: Group member changes
	
	// TODO: Homepage layout changes
	
	// TODO: Submission boxes
	
	
	function timecmp($a, $b)
	{
		//just a quick function to enable sorting of news based on time
	   if ($a['date'] == $b['date']) {
		   return 0;
	   }
	   return ($a['date'] > $b['date']) ? -1 : 1;
	}
	

}

?>
