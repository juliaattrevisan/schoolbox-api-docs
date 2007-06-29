<?php

/* Orginally developed by David Hammerton for MGS
 * Modified by James Leckie for inclusion in complete Intranet solution
 *
 * News Object - controls all news behaviour, insert, get, modify, delete.*/

class news
{

function news($topic = 0)
{
    $this->dbConnection = new dbManager;
}

function addNews($groupArray, $campusArray, $owner, $heading, $expires, $mainpage, $body, $file, $file_type, $file_name, $url1, $url2, $url3, $url4, $url5, $userInfo)
{
	$now = time();
	$expirestime = $now + (3600 * $expires);
	$query = "INSERT INTO news_items (item_heading, item_owner, item_datetime_posted, 
	item_datetime_expires, item_mainpage, item_body, item_link1, item_link2, item_link3, item_link4, item_link5)
	VALUES ('$heading', '$owner', FROM_UNIXTIME($now), 
	FROM_UNIXTIME($expirestime), '$mainpage', '$body', '$url1', '$url2', '$url3', '$url4', '$url5')";
	$this->dbConnection->dbDoQuery($query);
		
	$newsid = $this->dbConnection->dbInsertId();
	$this->addPerms($newsid, $groupArray, $campusArray);

}

function modNews($id, $campusArray, $groupArray, $heading, $expires, $mainpage, $body, $url1, $url2, $url3, $url4, $url5, $userInfo)
{
	//allow expiry time to be left as is if set to 0
	if($expires)
	{
		$now = time();
		$expirestime = $now + (3600 * $expires);
		$expirequery = "item_datetime_expires = FROM_UNIXTIME($expirestime),";
	}
	else
		$expirequery = "";
		
	$query = "UPDATE news_items
		SET item_heading = '$heading', $expirequery item_body = '$body',
		item_mainpage = '$mainpage', item_link1 = '$url1', item_link2 = '$url2', item_link3 = '$url3',
		item_link4 = '$url4', item_link5 = '$url5'
		WHERE item_id = '$id'";
	$this->dbConnection->dbDoQuery($query);
	
	$this->delPerms($id);
	$this->addPerms($id, $groupArray, $campusArray);
	
}

function delPerms($id)
{
	$query = "DELETE FROM newsGroup WHERE id = '$id'";
	$this->dbConnection->dbDoQuery($query);
	$query = "DELETE FROM newsCampus WHERE id = '$id'";
	$this->dbConnection->dbDoQuery($query);
}

function addPerms($id, $groupArray, $campusArray)
{
	if(is_array($groupArray))
	{
		foreach($groupArray as $group) 
		{
			$this->insertGroup($id, $group);
		}
	}
	else if(is_numeric($groupArray))
	{
		$this->insertGroup($id, $groupArray);
	}
	
	if(is_array($campusArray))
	{
		foreach($campusArray as $campus)
		{
			$this->insertCampus($id, $campus);
		}
	}
	else if(is_numeric($campusArray))
	{
		$this->insertCampus($id, $campusArray);
	}
	else
	{
		$this->insertCampus($id, 1);
	}
}

function insertGroup($id, $group)
{
	if(is_numeric($id) && is_numeric($group))
	{
		$query = "INSERT INTO newsGroup (id, gid) VALUES ($id, $group)";
		$this->dbConnection->dbDoQuery($query);
	}
}

function insertCampus($id, $campus)
{
	if(is_numeric($id) && is_numeric($campus))
	{
		$query = "INSERT INTO newsCampus (id, campus) VALUES ($id, $campus)";
		$this->dbConnection->dbDoQuery($query);
	}
}


function deleteNews($id, $userInfo)
{
	$SQL_QUERY = "UPDATE news_items
		SET item_deleted = '1'
		WHERE item_id = '$id'";
	$this->dbConnection->dbDoQuery($SQL_QUERY);
	$this->delPerms($id);
}

function getHeadlines()
{
    $query = "
	SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_datetime_posted
    FROM news_items 
    	JOIN newsCampus ON newsCampus.id = news_items.item_id 
			AND newsCampus.campus IN(".implode(',',$_SESSION['campus']).") 
		JOIN newsGroup ON newsGroup.id = news_items.item_id 
			AND newsGroup.gid = '".$_SESSION['gid']."'
    WHERE news_items.item_owner != '".$_SESSION['id']."' 
		AND news_items.item_datetime_expires > NOW() 
		AND news_items.item_deleted = 0 
	ORDER BY news_items.item_datetime_posted DESC
	";
	
	$shareResults = $this->dbConnection->dbGetAllRows($query);
	
    $query = "
	SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_datetime_posted
    FROM news_items 
    WHERE news_items.item_owner = '".$_SESSION['id']."' 
		AND news_items.item_datetime_expires > NOW() 
		AND news_items.item_deleted = 0 
	ORDER BY news_items.item_datetime_posted DESC
	";
	
	$userResults = $this->dbConnection->dbGetAllRows($query);
	
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


function timecmp($a, $b)
{
	//just a quick function to enable sorting of news based on time
   if ($a['item_datetime_posted'] == $b['item_datetime_posted']) {
       return 0;
   }
   return ($a['item_datetime_posted'] > $b['item_datetime_posted']) ? -1 : 1;
}

function getNews($id = 0)
{
 	$query = "
	SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_owner,
    	news_items.item_link1, news_items.item_link2, news_items.item_link3, news_items.item_link4, news_items.item_link5,
    	DATE_FORMAT(news_items.item_datetime_posted, '%W %M %e @ %l:%i%p') AS post_datetime, news_items.item_datetime_posted,
    	DATE_FORMAT(news_items.item_datetime_expires, '%W %M %e @ %l:%i%p') AS expire_datetime,
    	news_items.item_body, news_items.item_mainpage, 
    	user.lastname, user.title, user.id as uid, user.firstname
    FROM news_items
	JOIN user ON news_items.item_owner = user.id
    	JOIN newsCampus ON newsCampus.id = news_items.item_id 
			AND newsCampus.campus IN(".implode(',',$_SESSION['campus']).") 
			OR news_items.item_campus='4' 
		JOIN newsGroup ON newsGroup.id = news_items.item_id 
			AND newsGroup.gid = '".$_SESSION['gid']."'
    WHERE news_items.item_owner != '".$_SESSION['id']."' AND
		news_items.item_datetime_expires > NOW() 
		AND news_items.item_deleted = 0 
	"; 
    //get a single article by id if id submitted
    if($id)
            $query .="AND news_items.item_id = '$id'";

    $query .= " ORDER BY news_items.item_datetime_posted DESC;";

	$shareResults = $this->dbConnection->dbGetAllRows($query);
	
	 	$query = "
	SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_owner,
    	news_items.item_link1, news_items.item_link2, news_items.item_link3, news_items.item_link4, news_items.item_link5,
    	DATE_FORMAT(news_items.item_datetime_posted, '%W %M %e @ %l:%i%p') AS post_datetime, news_items.item_datetime_posted,
    	DATE_FORMAT(news_items.item_datetime_expires, '%W %M %e @ %l:%i%p') AS expire_datetime,
    	news_items.item_body, news_items.item_mainpage, 
    	user.lastname, user.title, user.firstname, user.id as uid
    FROM news_items
	JOIN user ON news_items.item_owner = user.id
    WHERE news_items.item_owner = '".$_SESSION['id']."'
		AND news_items.item_datetime_expires > NOW() 
		AND news_items.item_deleted = 0 
	"; 
    //get a single article by id if id submitted
    if($id)
            $query .="AND news_items.item_id = '$id'";

    $query .= " ORDER BY news_items.item_datetime_posted DESC;";
	
	$userResults = $this->dbConnection->dbGetAllRows($query);

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
	
	return $allNews;
}

function getOneNews($id)
{
	//this function mainly serves to populate the modify news form
	$SQL_QUERY = "SELECT item_id, item_heading, item_owner,
		item_datetime_posted, item_datetime_expires, item_mainpage, item_body,
		item_link1, item_link2, item_link3, item_link4, item_link5, item_deleted
		FROM news_items WHERE item_id = '$id'";
	$this->dbConnection->dbDoQuery($SQL_QUERY);
	if ($thisNews = $this->dbConnection->dbGetNextArray())
	{
		//get list of campus and groups that item is available for
		$query = "SELECT campus FROM newsCampus WHERE id = '$id'";
		$this->dbConnection->dbDoQuery($query);
		while($thisCampus = $this->dbConnection->dbGetNextArray())
		{
			$thisNews['item_campus'][] = $thisCampus['campus'];
		}
		$query = "SELECT gid FROM newsGroup WHERE id = '$id'";
		$this->dbConnection->dbDoQuery($query);
		while($thisCampus = $this->dbConnection->dbGetNextArray())
		{
			$thisNews['item_group'][] = $thisCampus['gid'];
		}
		return $thisNews;
	}
	else
		return false;
}

function getOwner($id)
{
	$SQL_QUERY = "SELECT item_owner FROM news_items WHERE item_id = '$id'";
	$this->dbConnection->dbDoQuery($SQL_QUERY);
	if ($owner = $this->dbConnection->dbGetNextArray())
		return $owner['item_owner'];
	else
		return false;
	 
}
}
?>
