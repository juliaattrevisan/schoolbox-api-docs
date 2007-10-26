<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//Provides database interface for news posts.  Allows creation, 
//deletion and modify of subject news posts. 

require_once($_SERVER['DOCUMENT_ROOT']."/include/html-parse.php");

class news
{

function news($code=0)
{
    $this->code = $code;
    $this->dbConnection = new dbManager;
}

function addNews($code, $owner, $heading, $expires, $body)
{
	$now = time();
	$expirestime = $now + (3600 * $expires);

	$SQL_QUERY = "INSERT INTO subject_news (code, heading, owner, datetime_posted, datetime_expires, body)
	VALUES ('$code', '$heading', '$owner', FROM_UNIXTIME($now), FROM_UNIXTIME($expirestime), '$body')";
	$this->dbConnection->dbDoQuery($SQL_QUERY);
}

function modNews($id, $code, $heading, $expires, $body)
{
	if($expires)
	{
		$now = time();
		$expirestime = $now + (3600 * $expires);

		$SQL_QUERY = "UPDATE subject_news
			SET code = '$code', heading = '$heading',
			datetime_expires = FROM_UNIXTIME($expirestime), body = '$body'
			WHERE id = '$id'";
	}
	else
	{
		$SQL_QUERY = "UPDATE subject_news
			SET code = '$code', heading = '$heading', body = '$body'
			WHERE id = '$id'";
	}
	
	$this->dbConnection->dbDoQuery($SQL_QUERY);
}

function deleteNews($id)
{
	$SQL_QUERY = "UPDATE subject_news SET deleted = '1' WHERE id = '$id'";
	$this->dbConnection->dbDoQuery($SQL_QUERY);
}

function getNews()
{
    $query = "SELECT subject_news.id, heading, owner, DATE_FORMAT(datetime_posted, '%W %M %e @ %l:%i%p') AS datetime,
    body, username, user.id as uid, lastname, title, firstname 
    FROM subject_news, user WHERE user.id = subject_news.owner AND code = '$this->code' AND datetime_expires > NOW() AND deleted = 0 
    ORDER BY datetime_posted DESC";

	return $this->dbConnection->dbGetAllRows($query);
}

function getNewsArchive()
{
    $query = "SELECT subject_news.id, heading, owner, DATE_FORMAT(datetime_posted, '%W %M %e %Y @ %l:%i%p') AS datetime,
    body, username, user.id as uid, lastname, title, firstname 
    FROM subject_news, user WHERE user.id = subject_news.owner AND code = '$this->code' AND deleted = 0 
    ORDER BY datetime_posted DESC";

	return $this->dbConnection->dbGetAllRows($query);
}

function getSingleNews($id)
{
  $SQL_QUERY = "SELECT owner, heading, body, code FROM subject_news WHERE id = '$id' AND deleted = 0";
  $this->dbConnection->dbDoQuery($SQL_QUERY);
  if($this->dbConnection->dbCountRows() == 1)
        return $this->dbConnection->dbGetNextArray();
  else
	return 0;
} 

}
?>
