<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/page.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

class SitePage extends Page {

	function SitePage($login=TRUE) {
		parent::Page();
		
		$this->db = new dbManager;
		$this->setTab('main');
		
		if($login)
			$this->initSession();

		$this->setSite();
	}
	
	
	function loadContent($pageFile, $execute=TRUE) {
		$this->pageName = $pageFile;
	
		/* sanitise data, force all 404s through pageNotFound() */
		if( !preg_match("/^[a-zA-Z0-9\/\-\_]+/", $pageFile) || $pageFile == 'common/404') {
			$this->pageNotFound();
		}
		
		$this->arg = array();
		$path = explode('/', trim($pageFile, '/'));
		$total = count($path);
	
		/* iterate backwards through the path */
		for ($i = 0; $i < $total; $i++) {
		
			$filename = rtrim(implode("/", $path), '/');
			
			if(is_file($_SERVER['DOCUMENT_ROOT'].'/'.$filename.'.php') && $execute) {
				include($_SERVER['DOCUMENT_ROOT'].'/'.$filename. '.php');
				return true;
			} elseif(is_file($_SERVER['DOCUMENT_ROOT'].'/'.$filename.'/index.php') && $execute) {
				include($_SERVER['DOCUMENT_ROOT'].'/'.$filename. '/index.php');
				return true;
			} else if(is_file($this->templateRoot . '/' . $this->getTemplate($filename . '.html'))) {
				$this->setFile(TEMPLATE_content, $filename . '.html');
				return true;
			} else if(is_file($this->templateRoot .'/'. $filename . '/index.html')) {
				$this->setFile(TEMPLATE_content, $filename . '/index.html');
				return true;
			}
			
			/* move last element to arguments */
			array_unshift($this->arg, array_pop($path));
		}
		
		$this->pageNotFound();
	}

	function setSite() {
		if(SITE_detect == 'domain') {
			//Get site templates via domain name
			if(preg_match('/([\w]+)\./', $_SERVER['HTTP_HOST'], $match)) {
				$this->site = $match[1];
				return true;
			}
		}
		else if(SITE_detect == 'campus') {
			//Get site template via users campus
			if(SITE_location == 'mgs') {
				if($_SESSION['campus'][0] == 3)
					$this->site = 'grimwade';
				else
					$this->site = 'mgs';

				return true;
			}
			else {
				$this->site = SITE_location;
			}
		}

		$this->site = SITE_location;
	
	}
	
	function outputPage($lite=FALSE) {
		if(!$lite) {
			$this->buildPage();
			$this->saveHistory();
		}
		
		$templateVar = array(	'TEMPLATE_YEAR' => date('Y'),
								'SITE_NAME'		=> SITE_name,
								'SITE_ADDRESS'	=> SITE_address,
								'SITE_GREETING' => greeting(),
								'SITE_LOGINNAME'=> formatName($_SESSION),
								'SITE_LOCATION'	=> $this->site,
							);
		$this->setVar($templateVar);
		
		$title = $this->getVar('TITLE');
		if (empty($title))
			$this->setTitle();
		
		$this->template->pparse('finaloutput', TEMPLATE_body);
	}
	
	function buildPage($customHead = true) {
	
		$this->groupHandler = new Group;
		
		$this->setFile(TEMPLATE_body, 'include/body.html' );
		$this->setFile('headerTpl', 'include/header.html' );
		$this->setFile('footerTpl', 'include/footer.html' );
		$this->setFile('bannerTpl', 'include/banner.html' );
		$this->setFile('navTpl', 'include/nav.html' );
		$this->setFile('tabTpl', 'include/tab.html' );
		
		$this->renderScriptIncludes();
		$this->renderSideNav();
		$this->renderTabBar();
		$this->renderGroupList();
		$this->renderSubjectList();
		$this->renderLinkList();
		$this->renderStudents();
		
		$this->parse('MAIN_HEADER_REGION', 'headerTpl');
		$this->parse('MAIN_FOOTER_REGION', 'footerTpl');
		$this->parse('MAIN_BANNER_REGION', 'bannerTpl');
		
		if ($_SESSION['id']) {
			$this->parse('MAIN_NAV_REGION', 'navTpl');
			$this->parse('MAIN_TAB_REGION', 'tabTpl');
		} else {
			$this->setVar('MAIN_NAV_REGION', '');
			$this->setVar('MAIN_TAB_REGION', '');
		}
		
		$this->parse('MAIN_CONTENT_REGION', TEMPLATE_content);
		
		if ($this->getVar('custom_head') && $customHead)
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
	
	function saveHistory() {
		/* Function saves user page history in session so we can go back to where they came from */
		if(!is_array($_SESSION['history']))
			$_SESSION['history'] = array();
		
		/* Dont handle duplicates (page refreshes) */
		if($_SESSION['history'][0] != $_SERVER['REQUEST_URI']) {
		
			array_unshift($_SESSION['history'], $_SERVER['REQUEST_URI']);
			
			if(count($_SESSION['history']) > 3)
				array_pop($_SESSION['history']);
		}
	}
	
	function backToTheFuture($page) {
		/* In Dans honour, time is ours */
		if(is_array($_SESSION['history']) ) {
			if(count($_SESSION['history']) >= $page)
				localRedir($_SESSION['history'][$page-1]);
			else 
				localRedir($_SESSION['history'][(count($_SESSION['history'])-1)]);
		}
		else /* Lost in space and time, just go home */
			localRedir('/');
		
	}

	function error($ec, $message = "") {
		
		require_once($_SERVER['DOCUMENT_ROOT'].'/../include/db.php');
		
		$db = new dbManager(SQL_db, 1);
	
		$query = "SELECT message FROM error WHERE id = '$ec'";
		$error = $db->getRow($query);
	
		$output = str_replace("%MESSAGE%", $message, $error['message']);
		
		header("HTTP/1.0 400 Bad Request");
		$this->setVar(TEMPLATE_content, '');
		
		$this->setFile(TEMPLATE_content, 'common/error.html');
		$this->setTitle('Error Encountered');
		$this->setVar("OUTPUT", $output);
		$this->buildPage(false);
		$this->outputPage(true);
		exit;
	
	}
	
	function renderSideNav() {
		//this section handles the drop down menu
		if($_SESSION['su'])
			$query = "SELECT sideMenu.id, parent, txt, url, target FROM sideMenu ORDER BY parent, sequence, txt";
		else if(is_array($_SESSION['campus'])) 
			$query = "	SELECT DISTINCT(sideMenu.id), parent, txt, url, target FROM sideMenu, sideMenuCampus, sideMenuGroup
          				WHERE (sideMenu.id = sideMenuCampus.id AND sideMenuCampus.campus IN (".implode(',',$_SESSION['campus']).") )
            				AND (sideMenu.id = sideMenuGroup.id AND sideMenuGroup.gid = '".$_SESSION['gid']."')
          				ORDER BY parent, sequence, txt";
		else
			$query = "	SELECT DISTINCT(sideMenu.id), parent, txt, url, target FROM sideMenu, sideMenuCampus, sideMenuGroup
          				WHERE (sideMenu.id = sideMenuCampus.id AND sideMenuCampus.campus = 1)
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

		/* output submission box links */
		$this->setBlock('tabTpl', 'duework_tab', 'dw_tab');
		$this->setBlock('tabTpl', 'submission_tab', 'sm_tab');
		if($this->groupHandler->isStaff() ) {
				$this->parse('sm_tab','submission_tab');
				$this->setVar('dw_tab','');
		}
		else if($this->groupHandler->isStudent()) {
				$this->parse('dw_tab','duework_tab');
				$this->setVar('sm_tab','');
		}
		else {
			$this->setVar('sm_tab','');
			$this->setVar('dw_tab','');
		}

		//this section handles the display of the webmail tab
		// if configuration states mail is available and user has access to local mail account display it
		$this->setBlock('tabTpl', 'webmail_tab', 'wm_tab');
        
		if((MAIL_enable && $_SESSION['local_email']) || 
                ($this->site == 'wenona' && $this->groupHandler->isStaff()) ||
                $this->site == 'ormond' ||
                $this->site == 'shore' && !($_SESSION['gid'] == 6 || $_SESSION['gid'] ==  7 || $_SESSION['gid'] == '8' )  ||
                $this->site == 'joeys'
                )
		{
			if($this->site == 'mgs' || $this->site == 'grimwade')
			{
				if($this->groupHandler->isStaff())
					$this->setVar("WEBMAIL_LOCATION", 'href="https://webmail.mgs.vic.edu.au/exchange/" rel="external"');
				else
					$this->setVar("WEBMAIL_LOCATION", 'href="/mail/"');
			}
			else if(SITE_location == 'ormond') 
			{
				if($_SESSION['exchange'])
					$this->setVar("WEBMAIL_LOCATION", 'href="http://owa.ormond.unimelb.edu.au" rel="external"');
				else
					$this->setVar('WEBMAIL_LOCATION', 'href="/mail/"');
			}
			else if($this->site == 'wenona') {
				if($this->groupHandler->isStaff()) {
					$this->setVar('WEBMAIL_LOCATION', 'href="https://webmail.wenona.nsw.edu.au/owa" rel="external"');
				}
				else {
					$this->setVar('WEBMAIL_LOCATION', 'href="/mail/"');
				}
			}
			else if($this->site == 'shore') {
				if($this->groupHandler->isStaff()) {
					if(strpos(getIP(),'192.168.') === FALSE)
						$this->setVar('WEBMAIL_LOCATION', 'href="https://mail.shore.nsw.edu.au/exchange/" rel="external"');
					else
						$this->setVar('WEBMAIL_LOCATION', 'href="http://shorexch01/exchange" rel="external"');
				}
				else if($this->groupHandler->isStudent()) {
					if(strpos(getIP(),'192.168.') === FALSE)
						$this->setVar('WEBMAIL_LOCATION', 'href="https://mail.student.shore.nsw.edu.au/exchange/" rel="external"');
					else
						$this->setVar('WEBMAIL_LOCATION', 'href="http://cassandra/exchange" rel="external"');
				}
			}
            else if($this->site == 'joeys') {
                $this->setVar('WEBMAIL_LOCATION', 'href="http://webmail/" rel="external"');
            }
            else if($this->site == 'mggs') {
                $this->setVar('WEBMAIL_LOCATION', 'href="https://webmail.mggs.vic.edu.au" rel="external"');
            }

			$this->parse('wm_tab','webmail_tab');
		}
		else
		{
			$this->setVar('wm_tab', '');
		}
		
		//custom code for each site is performed here	
		if($this->site == 'wenona')
		{
			require_once $_SERVER['DOCUMENT_ROOT'] . '/include/wenona.php';
			//provide encoded login details for intranet
			$this->setVar("ENC_LOGIN", urlencode(base64_encode(xorEncrypt(INTRANET_encryption_key, $_SESSION['username'].' '.$_SESSION['password'].' '.date('Y m d H i')))));
		
			$this->setBlock('tabTpl', 'portfolio_tab', 'pf_tab');
			if($this->groupHandler->isStaff() || $this->groupHandler->isStudent()) {
				$this->parse('pf_tab', 'portfolio_tab');
			}
			else {
				$this->setVar('pf_tab', '');
			}
		}
		if($this->site == 'shore') {
			$this->setBlock('tabTpl', 'myfiles_tab', 'mf_tab');
			if($this->groupHandler->isStaff()) {
				if(strpos(getIP(),'192.168.') === FALSE)
						$this->setVar('MYFILES_LOCATION', 'https://mail.shore.nsw.edu.au/fileway');
				else
						$this->setVar('MYFILES_LOCATION', 'http://shorexch01/fileway');

				$this->parse('mf_tab', 'myfiles_tab');
			}
			else if($this->groupHandler->isStudent()) {
				if(strpos(getIP(),'192.168.') === FALSE)
						$this->setVar('MYFILES_LOCATION', 'https://mail.student.shore.nsw.edu.au/fileway');
				else
						$this->setVar('MYFILES_LOCATION', 'http://cassandra/fileway');

				$this->parse('mf_tab', 'myfiles_tab');
			}
			else {
				$this->setVar('MYFILES_LOCATION', '#');
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
						'TAB_PORTFOLIO_ON'	=> '',
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
                  ORDER BY category.name
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
				if($this->groupHandler->isStaff())
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
					$this->setVar("MLINK", "<a href=\"".$link['url']."\"><span>".scrubTEXT($link['description'])."</span></a>");	
				}else{
					$this->setVar("MLINK", "<a href=\"/redir.php?url=".urlencode(urlencode($link['url']))."\" target=\"_blank\"><span>".scrubTEXT($link['description'])."</span></a>");
				}
				$this->parse("my_links", "mlink_block", true);
			}
		}
		else
			$this->setVar("my_links", "");
	}
	
	function renderStudents() {
		//my students section
		$this->setBlock('navTpl', 'mstudent', 'mstudent_section');
		$this->setBlock('mstudent', 'mstudent_block', 'my_students');
		
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
	
	function renderScriptIncludes() {
		$this->setBlock('headerTpl', 'header_javascript', 'header_javascript_block');
		$this->setBlock('headerTpl', 'header_css', 'header_css_block');
		
		if(is_array($this->jscript)) {
			foreach($this->jscript as $scriptOutput) {
				$this->setVar($scriptOutput);
				$this->parse('header_javascript_block','header_javascript', true);
			}
		}
		else
			$this->setVar('header_javascript_block', '');
		
		if(is_array($this->css)) {
			foreach($this->css as $cssOutput) {
				$this->setVar($cssOutput);
				$this->parse('header_css_block','header_css', true);
			}
		}
		else
			$this->setVar('header_css_block', '');
		
	}
	
	
	function setJavascript($file) {
		$this->jscript[] = array('HEADER_SCRIPT_LOCATION'=>$file);
	}
	
	function setCSS($file, $type='screen') {
		$this->css[] = array('HEADER_CSS_TYPE'=>$type,'HEADER_CSS_LOCATION'=>$file);
	}
	
	function setFile($handle, $file) {
		$this->template->setFile($handle, $this->getTemplate($file));
		
		if($handle == TEMPLATE_content) {
			$this->templateFile = $file;
			$this->setBlock($handle, "custom_head", "custom_head_block");
			$this->setVar('custom_head_block', '');
		}

	}
	
	function setTitle($title = '') {
		$this->setVar('TITLE', HEADING . (!empty($title) ? ' - '.scrubTEXT($title) : ''));
	}
	
	function getTemplate($template) {
		if(is_file($this->templateRoot.'/'.$this->site.'/'.$template))
			return $this->site.'/'.$template;
		else
			return 'core/'.$template;
	}
	
	function getVar($varname) {
		return $this->template->getVar($varname);
	}
	
	function localRedir($file) {
			$contype = conType();
			header("Location: ".$contype.$_SERVER['HTTP_HOST'].$file);
			exit;
	}
	
	function goLoginPage($error = '', $page = ''){
		$this->setSite();
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
		$this->setVar(TEMPLATE_content, '');
		$this->setFile(TEMPLATE_content, 'common/404.html');
		$this->setTitle('Page Not Found');
		
		$this->buildPage(FALSE);
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

	function dropDown($handle, $data, $selected = array(), $template = TEMPLATE_content, $mapping='') {
		$this->setBlock($template, $handle, $handle.'_block');
		$selected = is_array($selected) ? $selected : array($selected);
		if (is_array($data)) {
			foreach($data as $row) {
				foreach($row as $key => $value) {
					$mapping[$key]?$key=$mapping[$key]:'';
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

		$text = "<ul>\n";
		
		for($i=0; $list[$i]; $i++)
		{
			if($list[$i]['target'])
				$target = " target={$list[$i]['target']}";
			else $target = '';
			
			if(is_array($list[$i]['children'])) {
				$text .= '<li class="parent"><a href="'.$list[$i]['url'].'"'.$target.'><span>'.$list[$i]['txt'].'</span></a>';
				$text .= $this->__outputMenu($list[$i]['children'], ($level + 1));
			}
			else 
				$text .= '<li><a href="'.$list[$i]['url'].'"'.$target.'><span>'.$list[$i]['txt'].'</span></a>';
				
			$text .= "</li>\n";
		}
		$text .= "</ul>\n";
		return $text;
	}
	

}

?>
