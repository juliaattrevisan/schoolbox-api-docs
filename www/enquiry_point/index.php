<?

session_start();

require_once("../include/include.php");

class page {
	
	function page() {
		
		$userid = $_SESSION['id'];

		$this->db = new dbManager();
		$this->db->dbConnect();
		
		$this->t_body = new Template(TPL_directory);

		if($userid ==null) {
			$this->t_body->set_file("tplBODY", "modules/detention/enquiry_point/indexn.htm");
		}
		else
		{
			$this->t_body->set_file("tplBODY", "modules/detention/enquiry_point/index.htm");
		}
		
		if($no_user ==null) {
			$type ="Please Select";
			$enqum ="ns";
			include("include.php");
		}
			$enqu ="$_REQUEST[enqu]";
			include("include.php");
		
		$this->userdata($userid);
		
		$this->display();
		
	}
	
	function userdata($userid) {
		$sql ="SELECT * FROM intranet.user WHERE id = '$userid'";
		
		$row = $this->db->dbGetSingleRow($sql);

		$this->t_body->set_var($row);
		
		if($row[alt_email] !="") {
			$this->t_body->set_var("EMAIL", "$row[alt_email]");
		}
		else {
			$emailad ="$row[username]@mgs.vic.edu.au";
			$this->t_body->set_var("EMAIL", "$emailad");
		}
	}
	
	function display() {
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}


$page =new page();

?>
