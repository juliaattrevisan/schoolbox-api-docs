<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/page.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/permission.php';

class SitePage extends Page {

	function SitePage($login=TRUE) {
		parent::Page();
		$this->site = SITE_location;
		$this->db = new dbManager;
		$this->currentTab = "main";
		
		if($login)
			$this->initSession();
	}
	
	
	function loadContent($pageFile, $execute=TRUE) {
		$this->pageName = $pageFile;
		
		//max dir depth is 2 so anything after that must be arguments being passed to the script
		if( !preg_match("/^[a-zA-Z0-9\/\-\_]+/", $pageFile) ) {
			
			$this->pageNotFound();
		}

		$path = explode('/', $pageFile);
		$page = $pageFile;
		if(count($path) > 1) { 
			$page = $path[0];
			$this->arg = array_slice($path,1);
			foreach($this->arg as $key => $value) {
				if(!$value) unset($this->arg[$key]);
			}
		}
		$page = rtrim($page, '/');

		if (is_file($_SERVER['DOCUMENT_ROOT'].'/'.$page.'/'. $this->arg[0] . '.php') && $execute) {
			$page2 = array_shift($this->arg);
			include($_SERVER['DOCUMENT_ROOT'].'/'.$page.'/'. $page2 . '.php');
		}
		else if(is_file($_SERVER['DOCUMENT_ROOT'].'/'.$page . '.php') && $execute)
			include($_SERVER['DOCUMENT_ROOT'].'/'.$page . '.php');
		else if(is_file($_SERVER['DOCUMENT_ROOT'] . '/../templates/' . $this->getTemplate($page.'.html'))) {
			$this->setFile(TEMPLATE_content, $page.'.html');
		} else
			$this->pageNotFound();

	}
	
	
	function outputPage($lite=FALSE) {
		if(!$lite)
			$this->buildPage();
			
		$this->template->pparse('finaloutput', TEMPLATE_body);
	}
	
	function buildPage() {
	
		$this->setFile(TEMPLATE_body, 'include/body.html' );
		$this->setFile('headerTpl', 'include/header.html' );
		$this->setFile('footerTpl', 'include/footer.html' );
		$this->setFile('tabTpl', 'include/tab.html' );
		$this->setFile('navTpl', 'include/nav.html' );
		
		
		$this->renderSideNav();
		$this->renderTabBar();
		$this->renderGroupList();
		$this->renderSubjectList();
		$this->renderLinkList();
		$this->renderStudents();
		
		
		$this->parse('MAIN_HEADER_REGION', 'headerTpl');
		$this->parse('MAIN_FOOTER_REGION', 'footerTpl');
		$this->parse('MAIN_TAB_REGION', 'tabTpl');
		$this->parse('MAIN_NAV_REGION', 'navTpl');
		$this->parse('MAIN_CONTENT_REGION', TEMPLATE_content);
		
		if ($this->getVar('custom_head'))
			$this->parse('CUSTOM_HEADER_REGION', 'custom_head');
		else
			$this->setVar('CUSTOM_HEADER_REGION', '');	
	}
	
	function setTab($tab) {
		$this->currentTab = $tab;
	}
	
	function initSession() {
		session_start();
		if ( !isset($_SESSION['id']) )
			$this->goLoginPage();
		
		//if ip tracking enabled ensure user has same ip as when they logged in
		if(TRACKIP && $_SESSION['ip'] != getIP())
			$this->goLoginPage();
		
		//if session remember is disabled and check session has not expired
		if(!$_SESSION['remember'] && (($_SESSION['lasttime'] + SESSIONLENGTH) < mktime()))
			$this->goLoginPage();
		
		//reset session timer
		$_SESSION['lasttime'] = mktime();
	}
	
	function renderSideNav() {
		//this section handles the drop down menu
		if($_SESSION['su'])
			$query = "SELECT sideMenu.id, parent, txt, url, target FROM sideMenu ORDER BY parent, sequence, txt";
		else
			$query = "	SELECT DISTINCT(sideMenu.id), parent, txt, url, target FROM sideMenu, sideMenuCampus, sideMenuGroup
          				WHERE (sideMenu.id = sideMenuCampus.id AND sideMenuCampus.campus IN (".implode(',',$_SESSION['campus']).") )
            				AND (sideMenu.id = sideMenuGroup.id AND sideMenuGroup.gid = '".$_SESSION['gid']."')
          				ORDER BY parent, sequence, txt";

		$this->db->query($query);

		//initialise root of tree
		$list[0]['id'] = 0;

		//fetch each record and insert into tree
		while($menuitem = $this->db->getNextArray() ) {
			$list = $this->__insertChild($list, $menuitem);
		}

		//output tree with html
		$this->setVar("MCSSMENU", $this->__outputMenu($list[0]['children'], 1));
	}
	
	function renderTabBar() {
		
		//this section hanldes the display of the timetable tab
		$this->setBlock('tabTpl', 'timetable_tab', 'tt_tab');
		//if the have a timetable or their children have a timetable
		if($_SESSION['timetable'] || is_array($_SESSION['children'][0]['subjects']))
			$this->parse('tt_tab','timetable_tab'); 	
		else
			$this->setVar('tt_tab', '');
		
		//this section handles the display of the webmail tab
		// if configuration states mail is available and user has access to local mail account display it
		$this->setBlock('tabTpl', 'webmail_tab', 'wm_tab');
		
		if((MAIL_enable && $_SESSION['local_email']) || (SITE_location == 'wenona' && isStaff($_SESSION['gid'])) || SITE_location == 'ormond')
		{
			$this->parse('wm_tab','webmail_tab');
			
			if(SITE_location == 'mgs')
			{
				if(isStaff($_SESSION['gid']))
					$this->setVar("WEBMAIL_LOCATION", "href=\"https://webmail.mgs.vic.edu.au/exchange/\" target=\"_blank\"");
				else
					$this->setVar("WEBMAIL_LOCATION", "href=\"/mail.php\"");
			}
			else if(SITE_location == 'ormond') 
			{
				$this->setVar('WEBMAIL_LOCATION', 'href="/mail.php"');
			}
			else if(SITE_location == 'wenona') {
				if(isStaff($_SESSION['gid'])) {
					$this->setVar('WEBMAIL_LOCATION', 'href="http://webmail.wenona.nsw.edu.au/gw/webacc" target="_blank"');
					$this->setVar('WEBMAIL_TITLE', '<em>W</em>ebmail');
				}
				else {
					$this->setVar('WEBMAIL_LOCATION', 'href="/mail.php"');
					$this->setVar('WEBMAIL_TITLE', '<em>W</em>ebmail');
		
				}
			}
		}
		else
		{
			$this->setVar('wm_tab', '');
		}
		
		//custom code for each site is performed here	
		if(SITE_location == 'wenona')
		{
			require_once $_SERVER['DOCUMENT_ROOT'] . '/include/wenona.php';
			//provide encoded login details for intranet
			$this->setVar("ENC_LOGIN", base64_encode(xorEncrypt(INTRANET_encryption_key, $_SESSION['username'].' '.$_SESSION['password'].' '.date('Y m d H i'))));
		
			$this->setBlock('tabTpl', 'portfolio_tab', 'pf_tab');
			$this->setBlock('tabTpl', 'myfiles_tab', 'mf_tab');
			if(isStaff($_SESSION['gid'])) {
				$this->parse('pf_tab', 'portfolio_tab');
				$this->parse('mf_tab', 'myfiles_tab');
			}
			else if(isStudent($_SESSION['gid'])) {
				$this->parse('mf_tab', 'myfiles_tab');
				$this->setVar('pf_tab', '');
			}
			else {
				$this->setVar('pf_tab', '');
				$this->setVar('mf_tab', '');
			}
		}
		
		// choose tab to highlight
		$output = array('TAB_MAIN_ON'		=> '',
						'TAB_NEWS_ON'		=> '',
						'TAB_WEBMAIL_ON'	=> '',
						'TAB_CALENDAR_ON'	=> '',
						'TAB_TIMETABLE_ON'	=> '',
						'TAB_RESOURCES_ON'	=> '',
						'TAB_MYFILES_ON'	=> '',
						'TAB_' . strtoupper($this->currentTab) . '_ON'	=> ' navthis',
						);
						
		$this->setVar($output);
	}
	
	function renderGroupList() {
		//this section handle mygroups section in the template	
		$this->setBlock("navTpl", "mgroup", "mgroup_section");
		$this->setBlock("mgroup", "mgroup_block", "my_groups");
		
		$query = "SELECT category.name, userAccess.sid, userAccess.type 
				  FROM userAccess, category 
				  WHERE userAccess.uid = '".$_SESSION['id']."' 
				  AND userAccess.sid = category.id 
				  AND userAccess.type > 0	
				  AND category.template <> 0
			";
		$this->db->query($query);
		if($this->db->countRows() > 0)
		{
		
			while($group = $this->db->getNextArray())
			{
					$this->setVar("MGNAME", scrubTEXT($group['name']));
					$this->setVar("MGID", $group['sid']);	
					$this->parse("my_groups", "mgroup_block", true);
			}
			$this->parse("mgroup_section", "mgroup");
		}
		else
			$this->setVar("mgroup_section", "");
	}
	
	function renderSubjectList() {
		//this section handles the mysubjects section in the template 
		$this->setBlock("navTpl", "msubject", "msubject_section");
		$this->setBlock("msubject", "msubject_block", "my_subjects");
		
		if(is_array($_SESSION['subjects']))
		{
			foreach($_SESSION['subjects'] as $subject)
			{
				if(isStaff($_SESSION['gid']))
					$this->setVar("MSUBJECT", $subject['name']." (".$subject['code'].")");
				else		
					$this->setVar("MSUBJECT", $subject['name']);
		
				$this->setVar("MSUBCODE", urlencode(urlencode($subject['code'])));
				$this->parse("my_subjects","msubject_block",true); 
			}
			$this->parse("msubject_section","msubject");
		}
		else
			$this->setVar("msubject_section", "");
	}
	
	function renderLinkList() {
		$this->setBlock("navTpl", "mlink_block", "my_links");
		$query = "SELECT url, description FROM bookmarks WHERE owner = '".$_SESSION['id']."'";
		$this->db->query($query);
		if($this->db->countRows() > 0){
			while($link = $this->db->getNextArray()){
				if(isLocalURL($link['url'])){
					$this->setVar("MLINK", "<a href=\"".$link['url']."\">".scrubTEXT($link['description'])."</a>");	
				}else{
					$this->setVar("MLINK", "<a href=\"/redir.php?url=".urlencode(urlencode($link['url']))."\" target=\"_blank\">".scrubTEXT($link['description'])."</a>");
				}
				$this->parse("my_links", "mlink_block", true);
			}
		}
		else
			$this->setVar("my_links", "");
	}
	
	function renderStudents() {
		//my students section
		$this->setBlock("navTpl", "mstudent", "mstudent_section");
		$this->setBlock("mstudent", "mstudent_block", "my_students");
		
		if(is_array($_SESSION['children']))
		{
			foreach($_SESSION['children'] as $child)
			{
				$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
				$user = $this->db->getRow($query);
				$this->setVar("MSTUDENT_ID", $child['id']);
				$this->setVar("MSTUDENT_NAME", formatName($user,'{firstname}'));
				$this->setVar("MSTUDENT_YEAR", $user['year']);
		
				$this->parse("my_students", "mstudent_block", true);
			
			}
			$this->parse("mstudent_section", "mstudent");
		}
		else
			$this->setVar("mstudent_section", "");
	}
	
	function setFile($handle, $file) {
		$this->template->set_file($handle, $this->getTemplate($file));
		
		$contents = @file_get_contents($this->templateRoot . '/' . $this->getTemplate($file));
		if (strpos($contents, "<!-- BEGIN custom_head -->") !== false) {
			$this->setBlock($handle, "custom_head", "custom_head_block");
			$this->setVar('custom_head_block', '');
		}
	}
	
	function setTitle($title) {
		$this->setVar('TITLE', HEADING.' - '.scrubTEXT($title));
	}
	
	function getTemplate($template) {
		if(is_file($this->templateRoot.'/'.$this->site.'/'.$template))
			return $this->site.'/'.$template;
		else
			return 'core/'.$template;
	}
	
	function getVar($varname) {
		return $this->template->get_var($varname);
	}
	
	function localRedir($file) {
			$contype = conType();
			header("Location: ".$contype.$_SERVER['HTTP_HOST'].$file);
			exit;
	}
	
	function goLoginPage($error = '', $page = ''){
		$this->setFile(TEMPLATE_body, 'common/login.html');
		$this->setVar('TITLE', HEADING." - Login");
		
		if($page)
			$this->setVar('HIDDEN',rawurldecode($page));
		else
			$this->setVar('HIDDEN',$_SERVER['REQUEST_URI']);
		
		if($error)
			$this->setVar('ERROR', $error);
		else if(!$page && $_SERVER['REQUEST_URI'] != '/login/' && $_SERVER['REQUEST_URI'] != '/' )
			$this->setVar('ERROR', 'You are currently not logged in.  Your session has either expired or you are beginning a new session.  Please log in below to continue browsing the portal.');
		else
			$this->setVar('ERROR', 'To begin a new session please login with your username and password.');
			
		
		$this->outputPage(TRUE);
		exit;
	}

	function pageNotFound() {
		header("HTTP/1.0 404 Not Found");
		$this->setFile(TEMPLATE_body, 'common/404.html');
		$this->setTitle('Page Not Found');
		$this->outputPage(TRUE);
		exit;
	}

	function checkList($handle, $data, $selected = array()) {
		$this->setBlock(TEMPLATE_content, $handle, $handle.'_block');
		$selected = is_array($selected) ? $selected : array();
		if (is_array($data)) {
			foreach($data as $row) {
				foreach($row as $key => $value) {
					$this->setVar($key, $value);
					if(in_array($value, $selected))
						$this->setVar($key.'_SELECTED', 'checked="checked"');
					else
						$this->setVar($key.'_SELECTED', '');
				}
				$this->parse($handle.'_block', $handle, true); 
			}
		} else
			$this->setVar($handle.'_block', '');
	}

	function dropDown($handle, $data, $selected = array(), $template = TEMPLATE_content) {
		$this->setBlock($template, $handle, $handle.'_block');
		$selected = is_array($selected) ? $selected : array($selected);
		if (is_array($data)) {
			foreach($data as $row) {
				foreach($row as $key => $value) {
					$this->setVar($key, $value);
					if(in_array($value, $selected))
						$this->setVar($key.'_SELECTED', 'selected="selected"');
					else
						$this->setVar($key.'_SELECTED', '');
				}
				$this->parse($handle.'_block', $handle, true); 
			}
		} else
			$this->setVar($handle.'_block', '');
	}	
	
	function canRead($perm) {
		return $perm >= 1 ? true : false;
	}

	function canWrite($perm) {
		return $perm >= 2 ? true : false;
	}
	
	//Private Functions
	
	
	//function used to create data structure for drop down menu
	function __insertChild($list, $element)
	{
		for($i=0; $list[$i]; $i++)
		{
			//if item is parent insert item
			if($list[$i]['id'] == $element['parent'])
			{
				$list[$i]['children'][] = $element;
				return $list;
			}
			
			if(is_array($list[$i]['children']))
			{
				$sublist = $this->__insertChild($list[$i]['children'], $element);
				$list[$i]['children'] = $sublist;
			}
		}
		return $list;
	}

	function __outputMenu($list, $level)
	{
		if($level == 1)
			$text = "\n<ul  id=\"udm\" class=\"udm\">";
		else
			$text = "<ul class=\"ormondMenu\">";
		
		for($i=0; $list[$i]; $i++)
		{
			if($list[$i]['target'])
				$target = " target={$list[$i]['target']}";
			else $target = '';
				
			$text .= '<li class="ormondMenuItem"><a href="'.$list[$i]['url'].'"'.$target.'>'.$list[$i]['txt'].'</a>';
			if(is_array($list[$i]['children']))
				$text .= $this->__outputMenu($list[$i]['children'], ($level + 1));
			
			$text .= "</li>";
		}
		$text .= "</ul>\n";
		return $text;
	}
	

}

?>
