<?
require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");

class bulletin
{
	function bulletin($campusID)
	{
		$this->campusID = $campusID;
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/bulletin/index.htm");
		$this->t_body->set_var("TITLE", HEADING." - Bulletin");	
		$this->db = new dbManager;	
	}
	
	function getCampusBulletinNews($catID)
	{
		$sql = "SELECT * FROM subject_news n
				WHERE n.code = '{$catID}' AND deleted = 0
				ORDER BY datetime_posted DESC
				LIMIT 1";
		$row = 	$this->db->dbGetSingleRow($sql);
		
		
		if (strlen($row['body']) > 100)
		{
			$offset = 100;
			$pos = strpos($row['body'], '</p>', $offset);
			$result = substr($row['body'], 0, $pos);
		}
		else 
		{
			$result = $row['body'];
		}
				
		$body = "<div style='font-weight:bold'>{$row['heading']}</div>".
			$result."<a href='article.php?id={$row['id']}'>Click here for entire article</a>";
		return $body;
	}
	
	function outputLinkList($cat1, $cat2, $cat3)
	{
		$this->t_body->set_block("tplBODY", "links_block", "lnk_blck");
		
		$query = "SELECT description, url, comment FROM link 
			WHERE category = '{$cat1}' OR category = '{$cat2}' OR category = '{$cat3}'
		ORDER BY submitted DESC";

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){$this->t_body->set_var("lnk_blck", "");}
		else
		{
			while($queryArray = $this->db->dbGetNextArray())
			{
				$this->outputLink($queryArray);
			}
		}

	}	
	
	function outputLink($queryArray)
	{
		//set the url		
		if(isLocalURL($queryArray['url']))
			$this->t_body->set_var("LINKNAME", "<a href=\"".$queryArray['url']."\">".scrubTEXT($queryArray['description'])."</a>");
		else
			$this->t_body->set_var("LINKNAME", "<a href=\"/redir.php?url=".$queryArray['url']."\" target=\"_blank\">".scrubTEXT(			
				$queryArray['description'])."</a>");
				
		//set the description
		$this->t_body->set_var("LINK_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));

		$this->t_body->parse("lnk_blck", "links_block", true);
	}	
	
	function getCatIDFromName($catName)
	{
		$sql ="SELECT id FROM category WHERE name = '{$catName}'";
		$row = 	$this->db->dbGetSingleRow($sql);
		return $row['id'];
	}
	
	function doOutput()
	{
		$sql="SELECT * FROM luCampus WHERE id = '{$this->campusID}'";
		$row = $this->db->dbGetSingleRow($sql);
		$campusName = $row['name'];
		
		$headID = $this->getCatIDFromName("From the Headmaster - ({$campusName})");
		$chapID = $this->getCatIDFromName("The Chaplain Writes - ({$campusName})");
		$leadID = $this->getCatIDFromName("From the Director of Leadership - ({$campusName})");

		$this->t_body->set_var('CAMPUSNAME', $campusName);	
		$this->t_body->set_var('HEADMASTERNOTE', $this->getCampusBulletinNews($headID));		
		$this->t_body->set_var('CHAPLINNOTE', $this->getCampusBulletinNews($chapID));
		$this->t_body->set_var('LEADERSHIPNOTE', $this->getCampusBulletinNews($leadID));			
		$this->outputLinkList($headID, $chapID, $leadID);
		
	
		include("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}	
}

if(isset($_GET['campusID']))
	$page = new bulletin($_GET['campusID']);
else
	$page = new bulletin($_SESSION['campus'][0]);

$page->doOutput();

?>
