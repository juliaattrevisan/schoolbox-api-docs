<?
// default, MySQL class for db-management.

class dbManager
{

function dbManager($db = SQL_db)
{
        $this->host = SQL_host;
        $this->user = SQL_user;
        $this->passwd = SQL_passwd;
        $this->db = $db;
        $this->dbConnect();
}

function dbConnect()
{
	$this->dbf = mysql_connect($this->host, $this->user, $this->passwd) or 
		die("<br>Could not connect to database");
	mysql_select_db($this->db, $this->dbf);
}

function dbClose()
{
	mysql_close ($this->dbf);
}

function dbDoQuery($sql_q)
{
	$this->result = mysql_query($sql_q) or $mysql_eval_error = mysql_error();
	if ($mysql_eval_error) {
		die("<br>Query not successful &query=$mysql_eval_error");
		exit;
	}
	
	return $this->result;
}

function dbGetResult()
{
	return $this->result;
}

function dbInsertId()
{
	$return_id = mysql_insert_id($this->dbf);
	return $return_id;
}	

function dbGetNextArray($result = 0)
{
	if ($result)
	        $return_array = mysql_fetch_array($result);
	else
		$return_array = mysql_fetch_array($this->result);
        return $return_array;
}

function dbCountRows()
{
	$return_count = mysql_num_rows($this->result);
	return $return_count;
}

function dbCountRowsAffected()
{
	$return_count = mysql_affected_rows($this->dbf);
	return $return_count;
}

function dbEndQuery($result = 0)
{
	if ($result)
		mysql_free_result($result);
	else
		mysql_free_result($this->result);
}
}
?>
