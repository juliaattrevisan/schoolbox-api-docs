<?
require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("../include/permission.php");

class listModules
{
	function listModules()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/fixEvents.htm");
		$message = "";
		if(is_dir("../modules/"))
		{
			$dir = dir($_SERVER['DOCUMENT_ROOT']."/modules/");
			while (false !== ($file = $dir->read()))
			{
				if($file{0} != '.')
				{
					$message .= "<a href='/modules/$file/'>$file</a><br />";
				}
			}
		}
		$this->t_body->set_var("RESULT",$message);
		$this->t_body->set_var("TITLE","List Modules");
		include_once("../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$page = new listModules();
?>