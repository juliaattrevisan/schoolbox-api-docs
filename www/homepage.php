<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays homepage for a particular service
//this version is upgraded to allow custom configuration of a homepage

//check to see user is logged in
require_once('include/session.php');
require_once('include/include.php');
require_once('include/permission.php');
require_once('include/involved.php');


define('MAX_COLUMN_COUNT',2);

class OutPut
{
	var $enroled = 0;
 
	function showPage(&$db, $category, $enroled)
	{
		$this->db = $db;	
		$this->enroled = $enroled;

		$this->category = $category;
		$this->service = HOMEPAGESERVICE;
		//check perms first
		$this->checkPerms();		

		//get skeleton template
		$query = "SELECT name, code, homepage_config.* FROM category, homepage_config WHERE cid = id AND id = '$category'"; 		
		$skel_template = $this->db->dbGetSingleRow($query);
						
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file('tplBODY', 'homepages/homepage'.$skel_template['template'].'.htm');

		$this->code = $skel_template['code'];
		
		//setup css
		$this->outputCSS($skel_template);

		//output header information
		$this->outputHeader($skel_template['name']);

		$this->logPageView();

		//get list of homepage components, locations and config
		$query = "SELECT position, `column`, component, homepage_comp_location.config, name, method, filename 
				FROM homepage_comp_location, homepage_comp 
				WHERE component = id AND cid = '$category'
				ORDER BY position";

		$result = $this->db->dbDoQuery($query);
		$componentCount = 0;
		//for each component we must place it in postion then call the appropriate function to parse it
		for($componentCount = 0;$component = $this->db->dbGetNextArray($result);$componentCount++)
		{
			if($component['method'] != "")
			{

				$component['config'] = unserialize($component['config']);
				$this->t_body->set_file($component['name'].$componentCount, 'homepages/components/'.$component['filename']);
				$this->t_body->set_var('COMPONENT_ID', $component['component']);
				$this->t_body->set_block($component['name'].$componentCount, "config", "config_block");
				
				if(!empty($component['config']) && $this->perm >= 2)
				{			
					foreach($component['config'] as $name => $value)
					{
						if($name == 'size')
						{
							$this->t_body->set_block("config", "size", "size_block".$componentCount);
							for($i=0; $i<=10; $i++)
							{
								$this->t_body->set_var('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
								$this->t_body->set_var('COMPONENT_CONFIG_SIZE_VALUE', $i);
								$this->t_body->set_var('COMPONENT_CONFIG_SIZE_SELECTED', $i == $value ? ' selected' : '');
								$this->t_body->parse("size_block".$componentCount, "size", true);
							}
						}
						elseif($name == 'image')
						{
							$this->t_body->set_block("config", "image", "image_block".$componentCount);
							
							$query = "SELECT id, file.description FROM file LEFT JOIN allowedFileTypes ON mime_type = mime WHERE category = '{$this->category}' 
									  AND deleted = '0' AND INSTR(mime_type, 'image/') AND inline = 'true'
                					  ORDER BY submitted".$lim;
							$this->db->dbDoQuery($query);
							if($this->db->dbCountRows() > 0)
							{
								while($image = $this->db->dbGetNextArray())
								{
									$this->t_body->set_var('COMPONENT_CONFIG_IMAGE', $image['description']);
									$this->t_body->set_var('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $value ? ' selected' : '');
									$this->t_body->parse("image_block".$componentCount, "image", true);
								}
							}
						}
						else
							$this->t_body->set_var('COMPONENT_CONFIG_' . strtoupper($name), $value);
					}
					
					$this->t_body->parse("config_block", "config");	
				}
				else
					$this->t_body->set_var("config_block", "");	
				
				$func = $component['method'];
				if($this->$func($component['name'].$componentCount, $component['config'])) {
					$this->t_body->parse('COLUMN'.$component['column'], $component['name'].$componentCount, true);
					$columnFlag[$component['column']] = true;
				}
			}
		}
		
		//set any columns with nothing in them to null
		for($i=1; $i <= MAX_COLUMN_COUNT; $i++){
			if(!$columnFlag[$i])
				$this->t_body->set_var('COLUMN'.$i,'');
		}

		//output page
		include("include/page.php");

		$this->t_body->pparse("finaloutput", "tplBODY");

	}
		
	function checkPerms()
	{
		$this->perm = perm($this->category, $this->service);
		if($this->perm < 1)
			error(14);
	}


	function outputHeader($name)
	{
		$name = scrubTEXT($name);
	
		if($this->code)
		{
			$this->t_body->set_var("HEADING", $name." (".$this->code.")");	
			$this->t_body->set_var("CODE", urlencode($this->code));
		}
		else {
			$this->t_body->set_var("HEADING", $name);
			$this->t_body->set_var("CODE",'');
		}
		
		$this->t_body->set_var("ID", $this->category);
		
		$this->t_body->set_var("TITLE", HEADING." - ".$name);
		
		$this->t_body->set_block('tplBODY', 'modify_layout', 'modify_layout_block');
		$this->t_body->set_block("tplBODY", "edit_page", "edit_page_block");
	
		
		if($this->perm >= 2) {//has elevated priviliges, can write to page
			$this->t_body->parse("edit_page_block", "edit_page");
			$this->t_body->parse('modify_layout_block', 'modify_layout');
		}	
		else {
			$this->t_body->set_var("edit_page_block", "");
			$this->t_body->set_var('modify_layout_block', '');
		}
	}

	function folderManage($name, $config)
	{
		$this->t_body->set_block($name, "manage_page", "manage_page_block");
		$this->t_body->set_block($name, "stats_data", "stats_data_block");

		if($this->perm == 3) //is owner of area
			$this->t_body->parse("manage_page_block", "manage_page");	
		else
			$this->t_body->set_var("manage_page_block", "");
	
		if($this->perm >= 2) //has write access
			$this->t_body->parse("stats_data_block", "stats_data");	
		else
			$this->t_body->set_var("stats_data_block", "");
		
		return true;
	}				

	function outputCSS($page)
	{
		//now create css string
		$css = ".large_head{ ";
		$css .= "background-color: #".$page['large_head_bgcolour']."; ";
		$css .= "color: #".$page['large_head_fontcolour']."; ";
		$css .= "font-weight: bold; font-family: Verdana; }";
		
		$css .= ".large_text{ ";
		$css .= "background-color: #".$page['large_text_bgcolour']."; ";
		$css .= "color: #".$page['large_text_fontcolour']."; ";
		$css .= "font-family: Verdana; }";

		$css .= ".small_head{ ";
		$css .= "background-color: #".$page['small_head_bgcolour']."; ";
		$css .= "color: #".$page['small_head_fontcolour']."; ";
		$css .= "font-weight: bold; font-family: Verdana;}";
		
		$css .= ".small_text{ ";
		$css .= "background-color: #".$page['small_text_bgcolour']."; ";
		$css .= "color: #".$page['small_text_fontcolour']."; ";
		$css .= "font-family: Verdana;}";

		$this->t_body->set_var("CSS", $css);
	}		


	function logPageView()
	{
		$testquery = "SELECT datetime FROM homepage_log WHERE cid = '$this->category' AND user = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($testquery);

		if($row = $this->db->dbGetNextArray() )
		{
			$query = "UPDATE homepage_log SET datetime = NOW(), hitcount = hitcount + 1 WHERE cid = '$this->category'
			AND user = '".$_SESSION['id']."'";
		}
		else
		{
			$query = "INSERT INTO homepage_log (datetime, user, cid,hitcount) VALUES ( NOW() , 
			'".$_SESSION['id']."', '$this->category', '0')";
		}

		$this->db->dbDoQuery($query);
	}

	function initSubject($name, $config)
	{
		//output mangement/userlist link to owner of subject
		$this->t_body->set_block($name, "class_list", "class_list_block");
	
		if(SYNERGY_enable == false || $this->perm < 2 || $this->code == '' )
			$this->t_body->set_var("class_list_block", "");
		else
			$this->t_body->parse("class_list_block", "class_list");	
			
		return true;
	}
	
	function outputRSS($name, $config) {
		$this->t_body->set_block($name, "rss_stories", "rss_block");
		
		require_once('include/lastRSS.php');
		$rss = new lastRSS; 
		
		$rss->cache_dir = RSS_cache_dir; 
  		$rss->cache_time = RSS_cache_time; // one hour
  		$rss->stripHTML = true;
		$rss->items_limit = $config['size'];
		$headlineList = $rss->get($config['url']);
			
		$this->t_body->set_var('RSS_NAME', !empty($headlineList['title']) ? $headlineList['title'] : 'RSS Feed');
		
		//output headlines
		if(is_array($headlineList['items']) ) {
			foreach($headlineList['items'] as $item) {
				$this->t_body->set_var("RSS_TITLE", $item['title']);
				$this->t_body->set_var("RSS_URL", $item['link']); 
				$this->t_body->set_var("RSS_BRIEF", $item['description']);
				$this->t_body->parse("rss_block", "rss_stories", true);
	    	}
		}
		else
			$this->t_body->set_var("rss_block", '');
		
		return true;
	}

	
	function outputDuties($name, $config)
        {
                $this->t_body->set_block($name, "duties", "d_block");
                $sql = "SELECT l.*, d.user, d.date, d.cid FROM luDuties l LEFT JOIN duty d ON l.dutyTypeID = d.dutyTypeID 
			and d.date = now() where l.cid = ".$this->category ;
                $this->db->dbDoQuery($sql);
                $block = false;
                $this->t_body->set_var("DATE", 'Duties for '.date('l, jS \of F Y'));
                while($row = $this->db->dbGetNextArray())
                {
                        $this->t_body->set_var("DUTY", $row['name']);
                        $this->t_body->set_var("ASSIGNEDUSER", $row['user']);
                        $this->t_body->parse("d_block", "duties", true);
                        $block = true;
                }
                if($block == false)
                {
                        $this->t_body->set_var("DUTY", "No Duties set for today");
                        $this->t_body->set_var("ASSIGNEDUSER", '');
                        $this->t_body->parse("d_block", "duties", true);
                }
                $this->t_body->set_block($name, "edit_duties", "edit_duties_block");
                if($this->perm < 2)
                        $this->t_body->set_var("edit_duties_block", "");
                else
                        $this->t_body->parse("edit_duties_block", "edit_duties");
					
				return true;

        }

	function outputMembers($name, $config)
	{
		//output members of group
		$this->t_body->set_block($name, "members", "members_block");
		
		$query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email FROM user, userAccess
		WHERE id = uid AND service = '$this->service' AND sid = '$this->category' AND type > 0 ORDER BY lastname, firstname";
		$this->db->dbDoQuery($query);
		
		if($this->db->dbCountRows() > 0)
		{
			while($member = $this->db->dbGetNextArray())
			{			
				$name = formatName($member);
				$email = formatEmail($member);
				
				$this->t_body->set_var("MEMBER_NAME", "<a href=\"/search/userLookup.php?id={$member['uid']}\">$name</a>");
					
				if($member['type'] == 3)
					$name .= " (Group Admin.)";
						
				if($email)
					$emailall .= $email.",";
				
				$this->t_body->parse("members_block", "members", true);	
			}
			$this->t_body->set_var("REPLYTO", formatEmail($_SESSION));
			$this->t_body->set_var("EMAILALL", $emailall);
		}
		else
		{
			$this->t_body->set_var("members_block", "");
		}		       
		return true;
	}

	function outputManagers($name, $config)
	{

		//output managers
		$this->t_body->set_block($name, "staff_members", "staff_member_block");

		$query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email FROM user, userAccess
		WHERE id = uid AND service = '$this->service' AND sid = '$this->category' and type in(3,2) ORDER BY lastname, firstname";
		$this->db->dbDoQuery($query);
	
		if($this->db->dbCountRows() > 0)
		{
			while($manager = $this->db->dbGetNextArray())
			{
				$name = formatName($manager);
				$email = formatEmail($manager);
				
				$this->t_body->set_var("MEMBER_NAME", "<a href=\"/search/userLookup.php?id={$manager['uid']}\">$name</a>");
					
				if($manager['type'] == 3)
					$name .= " (Group Admin.)";
						
				if($email)
					$emailall .= $email.",";
				
				$this->t_body->parse("staff_member_block", "staff_members", true);

			}
			$this->t_body->set_var("EMAILALL", $emailall);
		}
		else
		{
			$this->t_body->set_var("staff_member_block", "");
		}
		return true;
	}

	function outputTeachers($name, $config)
	{
		// gets teachers involved in subject, outputs links to their addressbook page
		include('include/syn_classlist.php');
		// sql to add module :
		// INSERT INTO homepage_comp VALUES(2, 'outputTeachers', 'Class Teachers', 'teacher_s.htm')
		$this->t_body->set_block($name, "staff_teachers", "staff_teacher_block");
		
		if($this->code != "" && SYNERGY_enable)
		{	
		
			$listSyns = getTeacherName($this->code);
			
			if(is_array($listSyns)) {
				foreach($listSyns as $synid) {
					$query = "SELECT id, title, firstname, lastname, alt_email, local_email FROM user WHERE synergy_id = '$synid'";
					$teacher=$this->db->dbGetSingleRow($query);
					if(is_array($teacher)) {
						$name = formatName($teacher);
			
						if(SITE_location == 'wenona') {
							$this->t_body->set_var("MEMBER_NAME", $name);
						} else {
							$this->t_body->set_var("MEMBER_NAME", "<a href=\"/search/userLookup.php?id={$teacher['id']}\">{$name}</a>");
							$this->t_body->parse("staff_teacher_block", "staff_teachers", true);		
						}
					}
				}
			}
		}	
	}

	function outputNews($name, $config)
	{
		//news output
		$this->t_body->set_block($name, "news", "news_block");

		include("subject/subjectNews.php");
		$news = new news($this->category);
		
		$subnews = $news->getNews();
		if($subnews)
		{
			foreach($subnews as $article)
			{
				if($article['owner'] == $_SESSION['id'] || $_SESSION['su'] == 1 || $this->perm == 2)
					$this->t_body->set_var("MOD_NEWS", "<a href=\"/subject/modSubNews.php?modify=".$article['id']."\">Modify</a>");
				else
					$this->t_body->set_var("MOD_NEWS", "");
				
				$poster = formatName($article);
				
				$this->t_body->set_var("NEWS_HEADING", scrubTEXT($article['heading']));
				$this->t_body->set_var("NEWS_BODY", scrubHTML($article['body']));
				$this->t_body->set_var("EMAIL_CONTACT", "<a href=\"/search/userLookup.php?id={$article['uid']}\">$poster</a>");
				$this->t_body->set_var("POSTED_DATE", $article['datetime']);
				$this->t_body->parse("news_block", "news", true);
			}
		}
		else
		{
			$this->t_body->set_var("news_block", "");
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_news", "submit_news_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_news_block", "");
		else
			$this->t_body->parse("submit_news_block", "submit_news");	

		return true;
	} 
		
	function outputRandImage($name, $config)
    {
		//outputs an custom image and link to the homepage
		$this->t_body->set_block($name, "previewimage", "preview_block");

		//get image id
		$query = "SELECT id, comment FROM file WHERE category = {$this->category} AND deleted = '0' AND INSTR(mime_type, 'image/')
                  ORDER BY RAND() LIMIT 1";
		
		$this->db->dbDoQuery($query);                
		if($image = $this->db->dbGetNextArray())
		{
			//get link
			if($config['size']==0){$config['size']=6;}
				$imageSize=$config['size']*100;

			$imageurl = "cms/image.php?id={$image['id']}&height={$imageSize}&width={$imageSize}";
			
			$this->t_body->set_var("PREVIEW_IMAGE_URL", $imageurl);
			$this->t_body->set_var("PREVIEW_IMAGE_DESC", scrubTEXT($image['comment']));

			$this->t_body->parse("preview_block", "previewimage");
		}
		else
		{
			$this->t_body->set_var("preview_block", "");
		}
		return true;
	}

	function outputImage($name, $config)
	{
		//outputs an custom image and link to the homepage
		$this->t_body->set_block($name, "previewimage", "preview_block");
		
		// Make sure there a config option
		if(empty($config['image']))
			$config['image'] = 'Preview Image';

		//get image id
		$query = "SELECT id, comment FROM file WHERE category = '".$this->category."' AND deleted = '0' AND description LIKE '{$config['image']}'";
		$image = $this->db->dbGetSingleRow($query);
		if(!is_array($image)) {
			//try to find another image if we cant find the configured one	
			$query = "SELECT id, comment FROM file WHERE category = {$this->category} AND deleted = '0' AND INSTR(mime_type, 'image/')";
			$image = $this->db->dbGetSingleRow($query);
			
			if(!is_array($image)) {
				$this->t_body->set_var('preview_block', '');
				return true;
			}
		}
			
		if($config['pixel']>400){$config['pixel']=400;}
		if($config['pixel']<20){$config['pixel']=20;}
		
					
		$imageurl = "cms/image.php?id={$image['id']}&height={$config['pixel']}&width={$config['pixel']}";
		
		$this->t_body->set_var("PREVIEW_IMAGE_URL", $imageurl);
		$this->t_body->set_var("PREVIEW_IMAGE_DESC", scrubTEXT($image['comment']));
		$this->t_body->set_var("CONTENT_URL", $config['url']);
		
		$this->t_body->parse("preview_block", "previewimage");

		
		return true;
	}

	function outputDueWorkList($name, $config)
	{
		//due output
		$this->t_body->set_block($name, "duework", "duework_block");

		if( isStudent($_SESSION['gid']) )
		{
			$query = "
				SELECT	file.id, file.description, file.realname, file.comment,
					DATE_FORMAT(submission_box.date, '%D %b') AS date,
					UNIX_TIMESTAMP(submission_box.open_date) AS open, 
					UNIX_TIMESTAMP(submission_box.late_date) AS late,
					submission_box.id AS box_id, student_submission.owner, inline,
					file_type.name as type
				FROM file
				LEFT JOIN allowedFileTypes ON file.mime_type = mime
				LEFT JOIN submission_box ON	file.id = _box.fid
					AND submission_box.deleted = '0'
				LEFT JOIN student_submission ON	student_submission.box_id = submission_box.id 
					AND student_submission.owner = '".$_SESSION['id']."' 
				LEFT JOIN file_type ON submission_box.type = file_type.id
				WHERE file.category = '".$this->category."' AND file.deleted = '0' 
				ORDER BY submission_box.date DESC
			";
		
		}
		else
		{
			$query = "
			SELECT file.id, file.description, file.comment, 
				DATE_FORMAT(submission_box.date, '%D %b') AS date, 
				submission_box.id AS box_id, submission_box.owner, file.realname, inline,
				file_type.name as type
			FROM file  
				LEFT JOIN allowedFileTypes ON mime_type = mime
				LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0'
				LEFT JOIN file_type ON submission_box.type = file_type.id
			WHERE file.category = '".$this->category."' AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1 ){$this->t_body->set_var("duework_block", "");}
		else
		{
			while($queryArray = $this->db->dbGetNextArray() )
			{
				$this->outputDuework($queryArray);
			}
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_dw", "submit_dw_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_dw_block", "");
		else
			$this->t_body->parse("submit_dw_block", "submit_dw");	
			
		return true;

	}

	function outputHomeworkList($name, $config)
	{
		//homework output
		$this->t_body->set_block($name, "homework", "homework_block");

		if( isStudent($_SESSION['gid']) )
		{
			$query = "
				SELECT	file.id, file.description, file.realname, file.comment,
					DATE_FORMAT(submission_box.date, '%D %b') AS date,
					UNIX_TIMESTAMP(submission_box.open_date) AS open, 
					UNIX_TIMESTAMP(submission_box.late_date) AS late,
					submission_box.id AS box_id, student_submission.owner, inline
				FROM file
				LEFT JOIN allowedFileTypes ON file.mime_type = mime
				LEFT JOIN submission_box ON	file.id = _box.fid
					AND submission_box.deleted = '0'
				LEFT JOIN student_submission ON	student_submission.box_id = submission_box.id 
					AND student_submission.owner = '".$_SESSION['id']."' 
				WHERE file.category = '".$this->category."' AND submission_box.type = '3'
					AND file.deleted = '0' 
				ORDER BY submission_box.date DESC
			";
		
		}
		else
		{
			$query = "
			SELECT file.id, file.description, file.comment, 
				DATE_FORMAT(submission_box.date, '%D %b') AS date, 
				submission_box.id AS box_id, submission_box.owner, file.realname, inline
			FROM file  
				LEFT JOIN allowedFileTypes ON mime_type = mime
				LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0' 
			WHERE file.category = '".$this->category."' AND submission_box.type = '3' 
				AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1 ){$this->t_body->set_var("homework_block", "");}
		else
		{
			while($queryArray = $this->db->dbGetNextArray() )
			{
				$this->outputHomework($queryArray);
			}
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_hw", "submit_hw_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_hw_block", "");
		else
			$this->t_body->parse("submit_hw_block", "submit_hw");	
			
		return true;

	}
	
	function outputAssignList($name, $config)
	{
		//assignment
		$this->t_body->set_block($name, "assign", "assign_block");

		if(isStudent($_SESSION['gid']))
		{
			$query = "
			SELECT file.id, file.description, file.realname, file.comment, 
				DATE_FORMAT(submission_box.date, '%D %b') AS date, 
				UNIX_TIMESTAMP(submission_box.late_date) AS late, 
				UNIX_TIMESTAMP(submission_box.open_date) AS open,
				submission_box.id AS box_id, student_submission.owner, inline
			FROM file 
				LEFT JOIN allowedFileTypes ON file.mime_type = mime 
				LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0'
				LEFT JOIN student_submission ON  student_submission.box_id = submission_box.id 
					AND student_submission.owner = '".$_SESSION['id']."'
			WHERE file.category = '".$this->category."' AND submission_box.type = '2' AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}
		else
		{
			$query = "
			SELECT file.id, file.description, file.comment, 
				DATE_FORMAT(submission_box.date, '%D %b') AS date, 
				submission_box.id AS box_id, submission_box.owner, file.realname, inline 
			FROM file 
				LEFT JOIN allowedFileTypes ON mime_type = mime
				LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0' 
			WHERE file.category = '".$this->category."' AND submission_box.type = '2' AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 


		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1 ){$this->t_body->set_var("assign_block", "");}
		else
		{
			while($queryArray = $this->db->dbGetNextArray() )
			{
				$this->outputAssignment($queryArray);
			}
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_ass", "submit_ass_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_ass_block", "");
		else
			$this->t_body->parse("submit_ass_block", "submit_ass");	

		return true;
	}
	
	
	function outputLinkList($name, $config)
	{
		//link output
		$this->t_body->set_block($name, "link", "link_block");

		$query = "SELECT description, url, comment FROM link WHERE category = '".$this->category."' 
		ORDER BY submitted DESC";

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){$this->t_body->set_var("link_block", "");}
		else
		{
			while($queryArray = $this->db->dbGetNextArray() )
			{
				$this->outputLink($queryArray);
			}
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_link", "submit_link_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_link_block", "");
		else
			$this->t_body->parse("submit_link_block", "submit_link");	

		return true;
	}	
	
	function outputFileList($name, $config)
	{
		//file output
		$this->t_body->set_block($name, "files", "files_block");
	
		$query = "SELECT file.id as id, file.description, comment, realname, inline 
				  FROM file
				  LEFT JOIN allowedFileTypes ON mime_type = mime 
				  WHERE file.category = '".$this->category."' AND file.deleted = '0' ORDER BY submitted DESC";

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}";  

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){ $this->t_body->set_var("files_block", "");}
		else
		{
			while($queryArray = $this->db->dbGetNextArray() )
			{
				$this->outputFile($queryArray);
			}
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_file", "submit_file_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_file_block", "");
		else
			$this->t_body->parse("submit_file_block", "submit_file");	

		return true;
	}
		
	function outputFolderList($name, $config)
	{
		//file output
		$this->t_body->set_block($name, "folders", "folders_block");
	
		$query = "SELECT id, name, template FROM category 
		WHERE parent = '".$this->category."' ORDER BY name DESC";

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){ $this->t_body->set_var("folders_block", "");}
		else
		{
			while($queryArray = $this->db->dbGetNextArray() )
			{
				$this->outputFolder($queryArray);
			}
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_folder", "submit_folder_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_folder_block", "");
		else
			$this->t_body->parse("submit_folder_block", "submit_folder");	
	
		return true;
	}
		
	function outputImageGallery($name, $config)
	{
		define("IMAGESPERROW", 4);
        
        //output thumbnails
        $this->t_body->set_block($name, "thumb_row", "row_block");
        $this->t_body->set_block("thumb_row", "thumb_cell", "cell_block");
		
		if ($config['size'] == 0)
			$lim = "";
		else
			$lim = " LIMIT 1, ".IMAGESPERROW * $config['size'];
			
        $query = "SELECT id, file.description FROM file LEFT JOIN allowedFileTypes ON mime_type = mime WHERE category = '{$this->category}' 
					AND deleted = '0' AND INSTR(mime_type, 'image/') AND inline = 'true'
                	ORDER BY submitted".$lim;
		$this->db->dbDoQuery($query);

        $count = 1;
        $flag = 0;
		$this->t_body->set_var("CATID", $this->category);
		if($this->db->dbCountRows() > 0 )
		{ 
	       	while( $thumbnail = $this->db->dbGetNextArray() )
        	{
				$this->t_body->set_VAR("THUMBNAIL", "<a href=\"/cms/image.php?id={$thumbnail['id']}&height=550&width=600\" rel=\"lightbox[portal]\" title=\"{$thumbnail['description']}\">
                 <img src=\"/cms/image.php?id={$thumbnail['id']}&height=".THUMBNAIL_size."&width=".THUMBNAIL_size."&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\"></a>");

				if(($count % IMAGESPERROW) == 0)
				{
            		$this->t_body->parse("cell_block", "thumb_cell", true);
		    		$this->t_body->parse("row_block", "thumb_row", true);                		
		    		$flag = 1;
				}
            	else if($flag)
            	{
					$this->t_body->parse("cell_block", "thumb_cell", false);
					$flag = 0;
				}
				else
				{
					$this->t_body->parse("cell_block", "thumb_cell", true);
				}
	
				$count++;
			}
		
			$count--;
			
			if($count % IMAGESPERROW != 0)
			{
				for(;$count % IMAGESPERROW != 0; $count++)
				{
					$this->t_body->set_VAR("THUMBNAIL", "");
					$this->t_body->parse("cell_block", "thumb_cell", true);
				}
		
				$this->t_body->parse("row_block", "thumb_row", true);
			}
		}//end if
		else
		{
			$this->t_body->set_VAR("row_block", "");
		}
		return true;
	}

	function outputForumList($name, $config)
	{	
		//dont show forums to parents if they not a direct member of group
		if(isParent($_SESSION['gid']))
		{
			if(!isMember($this->category, $_SESSION['id']))
			{
				$this->t_body->set_var($name, "");
				return false;
			}
		}
	
		//forum recent posts
		$this->t_body->set_block($name, "forum", "forum_block");
	
		$query = "SELECT DISTINCT(thread) FROM forum WHERE cid='".$this->category."' AND deleted = '0' ORDER BY date DESC";
		
		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}";  

		$res = $this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){$this->t_body->set_var("forum_block", "");}
		else
		{
			while($curr = $this->db->dbGetNextArray($res))
			{
				$query = "SELECT id, heading FROM forum WHERE parent = '0' AND deleted ='0' AND thread = '".$curr['thread']."'";
				$this->db->dbDoQuery($query);
				$this->outputForum($this->db->dbGetNextArray());
			}
		}
		return true;
	}		

	function outputEventList($name, $config)
	{
		//event output
		$this->t_body->set_block($name, "group_events", "group_events_block");
	
		//event limit changed from posts to days
		if($config['size'] != 0 && $config['size'] != '')
				$where = " AND date < ADDDATE(CURDATE(), INTERVAL {$config['size']} DAY) ";
		else
				$where = "";
		
		
		$query = "SELECT id, DATE_FORMAT(date, '%a %D %b') as outputDate,
						DATE_FORMAT(date, '%Y') as year, DATE_FORMAT(date, '%c') as month, DATE_FORMAT(date, '%e') as day,
						TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTimeString, TIME_FORMAT(endTime, 
		'".CALENDAR_time_format."') as endTimeString,priority, note
				  FROM event WHERE access = '2' AND sid = '".$this->category."' AND( date >= NOW() $where) ORDER BY date, startTime";
		
		$this->db->dbDoQuery($query);	

		if($this->db->dbCountRows() < 1){$this->t_body->set_var("group_events_block", "");}
		else
		{
			require_once("calendar/calendar.php");
			$cal = new calendar;			

			while($queryArray = $this->db->dbGetNextArray() )
			{
				$queryArray['colour'] = $cal->displayColour($queryArray['priority']);
				$this->outputEvent($queryArray);
			}
		}


		//setup block for submission button
		$this->t_body->set_block($name, "submit_event", "submit_event_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_event_block", "");
		else
			$this->t_body->parse("submit_event_block", "submit_event");	
		
		return true;
	}

	function outputCalendarList($name, $config)
	{
		//event output
		$this->t_body->set_block($name, "group_events", "group_events_block");
	
		//event limit changed from posts to days
		if($config['size'] != 0 && $config['size'] != '')
				$where = " AND date < ADDDATE(CURDATE(), INTERVAL {$config['size']} DAY) ";
		else
				$where = "";
		
		
		
		$query = "SELECT id, DATE_FORMAT(date, '%a %D %b') as outputDate,
						DATE_FORMAT(date, '%Y') as year, DATE_FORMAT(date, '%c') as month, DATE_FORMAT(date, '%e') as day,
						TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTimeString, TIME_FORMAT(endTime, 
				'".CALENDAR_time_format."') as endTimeString,priority, note
				FROM event
        				JOIN eventCampus ON event.id = eventCampus.eventID
				WHERE access = 3 AND eventCampus.campusID = 3 AND( date >= NOW() $where) ORDER BY date, startTime";
		$this->db->dbDoQuery($query);	

		if($this->db->dbCountRows() < 1){$this->t_body->set_var("group_events_block", "");}
		else
		{
			require_once("calendar/calendar.php");
			$cal = new calendar;			

			while($queryArray = $this->db->dbGetNextArray() )
			{
				$queryArray['colour'] = $cal->displayColour($queryArray['priority']);
				$this->outputEvent($queryArray);
			}
		}


		//setup block for submission button
		$this->t_body->set_block($name, "submit_event", "submit_event_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_event_block", "");
		else
			$this->t_body->parse("submit_event_block", "submit_event");	
		
		return true;
	}
	
	function outputDuework($queryArray)
	{

		$url = "sendframe.php?id=".$queryArray['id'];

		if($queryArray['date'])
			$date = $queryArray['date'];
		else
			$date = "No date";

		//if staff or student is enrolled and there is work due provide a link
		if($this->enroled)
		{
			if(isStudent($_SESSION['gid']) && $queryArray['owner']=='' && $queryArray['late'] > date('U') && $queryArray['open'] < date('U') ) //student
				$dueDate = "<a href=\"/submission/submitWork.php?box_id=".$queryArray['box_id']."\">".$date."</a>";
			else if(isStaff($_SESSION['gid']) && $queryArray['owner'] == $_SESSION['id']) //if staff owns box provide link
				$dueDate = "<a href=\"/submission/viewSubmission.php?box_id=".$queryArray['box_id']."\">".$date."</a>";
			else
				$dueDate = $date;
		}
		else
			$dueDate = $date;
			
		$this->t_body->set_var("DUE_DATE", $dueDate);
	
		//set name
		if($queryArray['realname'] != "")
		{
			if($queryArray['inline'] == 'true')
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".scrubTEXT($queryArray['description'])."</a>";

			$this->t_body->set_var("DUE_NAME", $url);
		}
		else
			$this->t_body->set_var("DUE_NAME", scrubTEXT($queryArray['description']));
		
		$this->t_body->set_var("DUE_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));
		$this->t_body->set_var("DUE_TYPE", scrubTEXT($queryArray['type']));

		$this->t_body->parse("duework_block", "duework", true);

	}

	function outputHomework($queryArray)
	{

		$url = "sendframe.php?id=".$queryArray['id'];

		if($queryArray['date'])
			$date = $queryArray['date'];
		else
			$date = "No date";

		//if staff or student is enrolled and there is work due provide a link
		if($this->enroled)
		{
			if(isStudent($_SESSION['gid']) && $queryArray['owner']=='' && $queryArray['late'] > date('U') && $queryArray['open'] < date('U') ) //student
				$dueDate = "<a href=\"/submission/submitWork.php?box_id=".$queryArray['box_id']."\">".$date."</a>";
			else if(isStaff($_SESSION['gid']) && $queryArray['owner'] == $_SESSION['id']) //if staff owns box provide link
				$dueDate = "<a href=\"/submission/viewSubmission.php?box_id=".$queryArray['box_id']."\">".$date."</a>";
			else
				$dueDate = $date;
		}
		else
			$dueDate = $date;
			
		$this->t_body->set_var("HW_DATE", $dueDate);
	
		//set name
		if($queryArray['realname'] != "")
		{
			if($queryArray['inline'] == 'true')
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".scrubTEXT($queryArray['description'])."</a>";

			$this->t_body->set_var("HW_NAME", $url);
		}
		else
			$this->t_body->set_var("HW_NAME", scrubTEXT($queryArray['description']));
		
		$this->t_body->set_var("HW_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));

		$this->t_body->parse("homework_block", "homework", true);

	}

	function outputAssignment($queryArray)
	{

		if($queryArray['date'])
			$date = $queryArray['date'];
		else
			$date = "No date";

		//if staff or student is enrolled and there is work due provide a link
		if($this->enroled)
		{
			if(isStudent($_SESSION['gid']) && $queryArray['owner']=='' && $queryArray['late'] > date('U') && $queryArray['open'] < date('U')) //student
				$dueDate = '<a href="/submission/submitWork.php?box_id='.$queryArray['box_id'].'">'.$date.'</a>';
			else if(isStaff($_SESSION['gid']) && $queryArray['owner'] == $_SESSION['id']) //if staff owns box provide link
				$dueDate = '<a href="/submission/viewSubmission.php?box_id='.$queryArray['box_id'].'">'.$date.'</a>';
			else
				$dueDate = $date;
		}
		else
			$dueDate = $date;
			
			$this->t_body->set_var("ASS_DATE", $dueDate);

		//set name
		if($queryArray['realname'] != "")
		{
			if($queryArray['inline'] == 'true')
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".scrubTEXT($queryArray['description'])."</a>";

			$this->t_body->set_var("ASS_NAME", $url);
		}
		else
			$this->t_body->set_var("ASS_NAME", scrubTEXT($queryArray['description']));
		
		$this->t_body->set_var("ASS_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));

		$this->t_body->parse("assign_block", "assign", true);

	}

	function outputFile($queryArray)
	{		
		//set name
		if($queryArray['realname'] != "")
		{
			if($queryArray['inline'] == 'true')
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".scrubTEXT($queryArray['description'])."</a>";

			$this->t_body->set_var("FILE_NAME", $url);
		}
		else {
			$this->t_body->set_var("FILE_NAME", scrubTEXT($queryArray['description']));
		}
		
		$this->t_body->set_var("FILE_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));

		$this->t_body->parse("files_block", "files", true);

	}

	function outputFolder($queryArray)
	{
		//set name
		$this->t_body->set_var("FOLDER_NAME", "<a href=\"/browse.php?category=".$queryArray['id']."\">".scrubTEXT($queryArray['name'])."</a>");

		if($queryArray['template'])
			$this->t_body->set_var("HOMEPAGE_LINK", "<a href=\"/homepage.php?category=".$queryArray['id']."\">homepage</a>");
		else
			$this->t_body->set_var("HOMEPAGE_LINK", "");

		$this->t_body->parse("folders_block", "folders", true);

	}

	function outputForum($queryArray)
	{
		$url = "/forum/viewForum.php?msgid=".$queryArray['id']."&cid=".$this->category;
		
		//set name
		if($queryArray['id'] == "")
			$this->t_body->set_var("FORUM_HEAD", scrubTEXT($queryArray['heading']));
		else
			$this->t_body->set_var("FORUM_HEAD", "<a href=\"$url\">".scrubTEXT($queryArray['heading'])."</a>");

		$this->t_body->parse("forum_block", "forum", true);
	}

		

	function outputLink($queryArray)
	{
		//set the url		
		if(isLocalURL($queryArray['url']))
			$this->t_body->set_var("LINK_NAME", "<a href=\"".$queryArray['url']."\">".scrubTEXT($queryArray['description'])."</a>");
		else
			$this->t_body->set_var("LINK_NAME", "<a href=\"/redir.php?url=".$queryArray['url']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>");
		
		//set the description
		$this->t_body->set_var("LINK_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));

		$this->t_body->parse("link_block", "link", true);
	}
	
	function outputEvent($queryArray)
	{
		$this->t_body->set_var("CAL_DATE", $queryArray['outputDate']);
		$this->t_body->set_var("CAL_YEAR", $queryArray['year']);
		$this->t_body->set_var("CAL_MONTH", $queryArray['month']);
		$this->t_body->set_var("CAL_DAY", $queryArray['day']);
	
		if($queryArray['startTimeString'] == "0:00" || $queryArray['startTimeString'] == "")
				$this->t_body->set_var("CAL_TIME", "");
		else if($queryArray['endTimeString'] == "0:00")
				$this->t_body->set_var("CAL_TIME", $queryArray['startTimeString']);
		else
				$this->t_body->set_var("CAL_TIME", $queryArray['startTimeString']." - ".$queryArray['endTimeString']);

		$this->t_body->set_var("CAL_COLOUR",$queryArray['colour']);
		
		$this->t_body->set_var("CAL_NOTE", nl2br(scrubTEXT($queryArray['note'])));

		$this->t_body->parse("group_events_block", "group_events", true);

	}

	function outputDropBox($name, $config) {
	
		//output drop box
		$this->t_body->set_block($name, "drop_student", "drop_student_block");
		$this->t_body->set_block($name, "drop_staff", "drop_staff_block");
		$this->t_body->set_block("drop_staff", "drop_file", "drop_file_block");
	
		if($this->perm < 2) {
			$this->t_body->parse("drop_student_block", "drop_student");
			$this->t_body->set_var("drop_staff_block", "");
		}
		else {
			$this->t_body->set_var("drop_student_block", "");
			require_once("subject/dropbox.php");
			$dropbox = new dropbox;
			$fileList = $dropbox->getFileList($this->category);
			if(is_array($fileList)) {
				foreach($fileList as $file) {
					$output = array('DROPBOX_DESCRIPTION' => $file['description'],
									'DROPBOX_ID'		=> $file['id'],
									'DROP_SUBMITTED_BY' => formatName($file),
									'DROP_SUBMITTED_TIME'=>$file['subdate'],
									);
					$this->t_body->set_var($output);
					$this->t_body->parse("drop_file_block", "drop_file", true);
				}
			} 
			else 
				$this->t_body->set_var("drop_file_block", "");
			
			$this->t_body->parse("drop_staff_block", "drop_staff");
			
		}
		return true;
	}
}

$myPage = new OutPut();
$db = new dbManager;

if(isset($_GET['code']) && $_GET['code'])
{
	$code = urldecode($_GET['code']);
	$query = "SELECT id FROM category WHERE code LIKE '$code'";
	$db->dbDoQuery($query);
	if($db->dbCountRows() > 0)
	{
		$enroled = involvedInSubject($code);
		$resultArray = $db->dbGetNextArray();
		$myPage->showPage(&$db, $resultArray['id'], $enroled);
	}
	else
		error(18);
}
else if(isset($_GET['category']) && $_GET['category'])
{
	//check category has a template or code associated with it
	$query = "SELECT template, code FROM category WHERE id = '".$_GET['category']."'";
	$db->dbDoQuery($query);
	if($db->dbCountRows() > 0)
	{

		$resultArray = $db->dbGetNextArray();
		if($resultArray['code'])
		{
			$enroled = involvedInSubject($resultArray['code']);
			$myPage->showPage(&$db, $_GET['category'], $enroled);
		}
		else if($resultArray['template'])
		{
			$enroled = involvedInGroup($_GET['category']);
			$myPage->showPage(&$db, $_GET['category'], $enroled);
		}
		else
			error(18);
			
	}
	else
		error(18);
}
else
	error(2);

?>
