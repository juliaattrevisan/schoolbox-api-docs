<?php
// Mark Parncutt 20 Jan 2005
class detNav {
	var $pages;
	var $access;

	function detNav($access=false) {
		$this->pages = array(
			array(3,'viewDet.php','View Detentions'),
			array(2,'modifyDet.php','Add Det'),
			array(2,'attend.php','Attendances'),
			array(4,'classlists.php','Det lists'),
			array(3,'detList.php','History'),
			array(4,'admin.php','Administration')
		);

		if ($access) {
			$this->access = $access;
		} else {
			$this->access = permType();
		}
		if (isStaff($_SESSION['gid']) && $this->access < 2)
			$this->access = 2;
	}

	function getNav() {
		if ($this->access == 1) {
			return "";
		}
		$curpage = basename($_SERVER['PHP_SELF']);
		$pagehtml = array();
		foreach ($this->pages as $page) {
			if ($this->access >= $page[0]) {
				if ($page[1] == $curpage) {
					$pagehtml[] = "<strong>".$page[2]."</strong>";
				} else {
					$pagehtml[] = "<a href=\"".$page[1]."\">".$page[2]."</a>";
				}
			}
		}
		return implode(" / ",$pagehtml);
	}
}

class detAdminNav {
	var $pages;
	var $access;

	function getAdminNav($offences = false) {
		if ($offences)
			return "<a href=\"admin.php\">Date administration</a> / <strong>Offence administration</strong>\n";
		else
			return "<strong>Date administration</strong> / <a href=\"admin.php?off=1\">Offence administration</a>\n";
	}
}
?>
