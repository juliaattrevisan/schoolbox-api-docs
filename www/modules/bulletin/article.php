<?
require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");

class article
{
	function article($id)
	{
		$this->id = $id;
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/bulletin/article.htm");
		$this->t_body->set_var("TITLE", HEADING." - Bulletin");	
		$this->db = new dbManager();	
	}
	
	function doOutput()
	{
		$sql = "SELECT body, heading, c.name FROM subject_news n
				JOIN category c ON c.id = n.code
				WHERE n.id = {$this->id}";	
		$row = $this->db->getRow($sql);		
		
		$this->t_body->set_var('HEADING2', $row['heading']);
		$this->t_body->set_var('HEADING1', $row['name']);
		$this->t_body->set_var('BODY', $row['body']);		
	
		include("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}	
}

if(isset($_GET['id']))
{
	$page = new article($_GET['id']);
	$page->doOutput();
}
else
{
	error(2); //not enough info was supplied
}

?>