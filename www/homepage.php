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
require_once('include/fileTypes.php');

class OutPut
{
	var $enroled = 0;
 
	function showPage(&$db, $category, $enroled)
	{

		$this->db = $db;	
		$this->enroled = $enroled;

		$this->category = $category;
		$this->service = 1;
		//check perms first
		$this->checkPerms();		

		//get skeleton template
		$query = "SELECT name, homepage_config.* FROM category, homepage_config WHERE cid = id AND id = '$category'"; 		
		$this->db->dbDoQuery($query);
		$skel_template = $this->db->dbGetNextArray();
						
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file('tplBODY', 'homepages/homepage'.$skel_template['template'].'.htm');
		//$this->t_body->debug = 5;
		//setup css
		$this->outputCSS($skel_template);
		//$this->t_body->set_var("CSS", $skel_template['css']);

		//output header information
		$this->outputHeader($skel_template['name']);

		$this->logPageView();



		//get list of homepage components, locations and size
		$query = "SELECT position, component, homepage_comp_location.size, name, method, filename 
				FROM homepage_comp_location, homepage_comp 
				WHERE component = id AND cid = '$category'";

		$result = $this->db->dbDoQuery($query);
		$componentCount = 0;
		//for each component we must place it in postion then call the appropriate function to parse it
		for($componentCount = 0;$component = $this->db->dbGetNextArray($result);$componentCount++)
		{
			if($component['method'] != "")
			{
				$this->t_body->set_file($component['name'].$componentCount, "homepages/components/".$component['filename']);

				$func = $component['method'];
				$this->$func($component['name'].$componentCount, $component['size']);
			
				$this->t_body->parse("POS".$component['position'], $component['name'].$componentCount);
			}
			else   //nothing to be put in this postion so make it blank
				$this->t_body->set_var("POS".$component['position'], "");
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
		$name = htmlentities($name);
		$this->t_body->set_var("HEADING", $name);
		
		$this->t_body->set_var("ID", $this->category);
		
		$this->t_body->set_var("TITLE", HEADING." - ".$name);

	}

	function folderManage($name, $size)
	{
		$this->t_body->set_block($name, "manage_page", "manage_page_block");
		$this->t_body->set_block($name, "edit_page", "edit_page_block");

		if($this->perm == 3) //is owner of area
			$this->t_body->parse("manage_page_block", "manage_page");	
		else
			$this->t_body->set_var("manage_page_block", "");

		if($this->perm >= 2) //has elevated priviliges, can write to page
			$this->t_body->parse("edit_page_block", "edit_page");	
		else
			$this->t_body->set_var("edit_page_block", "");
	}				

	function outputCSS($page)
	{
		//first create colour table
		$query = "SELECT id, hex FROM homepage_colours";
		$this->db->dbDoQuery($query);
	
		while( $colour = $this->db->dbGetNextArray() )
		{
			$ctable[$colour['id']] = $colour['hex'];
		}

		//now create css string
		$css = ".large_head{ ";
		$css .= "background-color: ".$ctable[$page['large_head_bgcolour']]."; ";
		$css .= "color: ".$ctable[$page['large_head_fontcolour']]."; ";
		$css .= "font-weight: bold; font-family: Verdana; }";
		
		$css .= ".large_text{ ";
		$css .= "background-color: ".$ctable[$page['large_text_bgcolour']]."; ";
		$css .= "color: ".$ctable[$page['large_text_fontcolour']]."; ";
		$css .= "font-family: Verdana; }";

		$css .= ".small_head{ ";
		$css .= "background-color: ".$ctable[$page['small_head_bgcolour']]."; ";
		$css .= "color: ".$ctable[$page['small_head_fontcolour']]."; ";
		$css .= "font-weight: bold; font-family: Verdana; font-size: 11px}";
		
		$css .= ".small_text{ ";
		$css .= "background-color: ".$ctable[$page['small_text_bgcolour']]."; ";
		$css .= "color: ".$ctable[$page['small_text_fontcolour']]."; ";
		$css .= "font-family: Verdana; font-size: 11px}";

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

	function initSubject($name, $size)
	{
		$query = "SELECT name, code FROM category WHERE id = '".$this->category."'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
	
                if($result['code'] == "")
                        $this->t_body->set_var("HEADING", htmlentities($result['name']));
                else
                {
		        $this->t_body->set_var("HEADING", htmlentities($result['name'])." (".$result["code"].")");	
			$this->t_body->set_var("CODE", urlencode($result['code']));
		}

		//$this->t_body->set_var("TEACHER", $result['teacher']); 

		//output mangement/userlist link to owner of subject
		$this->t_body->set_block($name, "class_list", "class_list_block");
		if(SYNERGY_enable == 0 || $this->perm < 2 || $result['code'] == "" )
			$this->t_body->set_var("class_list_block", "");
		else
			$this->t_body->parse("class_list_block", "class_list");	
	}
	
	function outputMembers($name, $size)
	{
		//output members of group
		$this->t_body->set_block($name, "members", "members_block");
		
                $query = "SELECT firstname, lastname, type, username, local_email, alt_email FROM user, userAccess
                WHERE id = uid AND service = '$this->service' AND sid = '$this->category' AND type > 0 ORDER BY lastname, firstname";
                $this->db->dbDoQuery($query);
                
		if($this->db->dbCountRows() > 0)
		{
                	while($member = $this->db->dbGetNextArray())
                	{			
				$name = $member['firstname']." ".$member['lastname'];	
				if($member['type'] == 3)
					$name .= " (Group Admin.)";

		                if($member['local_email'])
                		        $email = $member['username']."@".MAIL_address;
                		else if($member['alt_email'])
                        		$email = $member['alt_email'];
                		else
                        		$email = "";
								
				if($email)
				{
					$this->t_body->set_var("MEMBER_NAME", "<a href=\"{$_SESSION['mailhandle']}{$email}\">{$name}</a>");
					$emailall .= $email.",";
				}
				else
					$this->t_body->set_var("MEMBER_NAME", $name);
				
               			$this->t_body->parse("members_block", "members", true);	
			}
			$this->t_body->set_var("EMAILALL", $emailall);
		}
		else
		{
			$this->t_body->set_var("members_block", "");
		}		       
                    
	}

	function outputManagers($name, $size)
	{

		//output managers
		$this->t_body->set_block($name, "staff_members", "staff_member_block");

		$query = "SELECT firstname, lastname, type, username, local_email, alt_email FROM user, userAccess
		WHERE id = uid AND service = '$this->service' AND sid = '$this->category' and type in(3,2) ORDER BY lastname, firstname";
		$this->db->dbDoQuery($query);
	
		if($this->db->dbCountRows() > 0)
		{
			while($manager = $this->db->dbGetNextArray())
			{
				$name = $manager['firstname']." ".$manager['lastname'];	
				if($manager['type'] == 3)
					$name .= " (Group Admin.)";

		                if($manager['local_email'])
                		        $email = $manager['username']."@".MAIL_address;
                		else if($manager['alt_email'])
                        		$email = $manager['alt_email'];
                		else
                        		$email = "";
								
				if($email)
				{
					$this->t_body->set_var("MEMBER_NAME", "<a href=\"{$_SESSION['mailhandle']}{$email}\">{$name}</a>");
					$emailall .= $email.",";
				}
				else
					$this->t_body->set_var("MEMBER_NAME", $name);
				
				$this->t_body->parse("staff_member_block", "staff_members", true);

			}
			$this->t_body->set_var("EMAILALL", $emailall);
		}
		else
		{
			$this->t_body->set_var("staff_member_block", "");
		}
				
	}


	function outputNews($name, $size)
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
				if($article['Owner'] == $_SESSION['id'] || $_SESSION['su'] == 1 || $this->perm == 3)
					$this->t_body->set_var("MOD_NEWS", "<a href=\"/subject/modSubNews.php?modify=".$article['Id']."\">Modify</a>");
				else
					$this->t_body->set_var("MOD_NEWS", "");
				$this->t_body->set_var("NEWS_HEADING", htmlentities($article['Heading']));
				$this->t_body->set_var("NEWS_BODY", $article['Body']);
				if($article['Contact'])
					$this->t_body->set_var("EMAIL_CONTACT", "<a href=\"{$_SESSION['mailhandle']}{$article['Contact']}\">{$article['Poster']}</a>");
				else
					$this->t_body->set_var("EMAIL_CONTACT", $article['Poster']);
		
				$this->t_body->set_var("POSTED_DATE", $article['Datetime']);
				$this->t_body->parse("news_block", "news", true);
			}
		}
		else
		{
			$this->t_body->set_var("news_block", "<tr><td>No news available.</td></tr>");
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_news", "submit_news_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_news_block", "");
		else
			$this->t_body->parse("submit_news_block", "submit_news");	

	} 
		
	function outputRandImage($name, $size)
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
			if($size==0){$size=6;}
				$imageSize=$size*100;
						
			$imageurl = "cms/image.php?id={$image['id']}&height={$imageSize}&width={$imageSize}";
			
			$this->t_body->set_var("PREVIEW_IMAGE_URL", $imageurl);
			$this->t_body->set_var("PREVIEW_IMAGE_DESC", $image['comment']);
	        $this->t_body->set_var("CONTENT_URL", $link['url']);

			$this->t_body->parse("preview_block", "previewimage");
		}
		else
		{
			$this->t_body->set_var("preview_block", "");
		}
	}

	function outputImage($name, $size)
	{
		//outputs an custom image and link to the homepage
		$this->t_body->set_block($name, "previewimage", "preview_block");

		//get image id
		$query = "SELECT id, comment FROM file WHERE category = '".$this->category."' AND deleted = '0' AND description LIKE 'Preview Image'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		{
			$image = $this->db->dbGetNextArray();
			
			//get link
			$query = "SELECT url FROM link WHERE category = '".$this->category."' AND description LIKE 'Intranet Site'";
			$this->db->dbDoQuery($query);
			$link = $this->db->dbGetNextArray(); 
			
			if($size==0){$size=6;}
				$imageSize=$size*100;
						
			$imageurl = "cms/image.php?id={$image['id']}&height={$imageSize}&width={$imageSize}";

			$this->t_body->set_var("PREVIEW_IMAGE_URL", $imageurl);
			$this->t_body->set_var("PREVIEW_IMAGE_DESC", $image['comment']);
			$this->t_body->set_var("CONTENT_URL", $link['url']);

                        $this->t_body->parse("preview_block", "previewimage");

		}
		else
			$this->t_body->set_var("preview_block", "");

	}

	function outputHomeworkList($name, $size)
	{
		//homework output
		$this->t_body->set_block($name, "homework", "homework_block");

		if( isStudent($_SESSION['gid']) )
		{
			$query = "
				SELECT	file.id,
					file.description,
					file.realname,
					file.comment,
					DATE_FORMAT(submission_box.date, '%D %b') AS date, 
					UNIX_TIMESTAMP(submission_box.late_date) AS late,
					submission_box.id AS box_id,
					student_submission.owner, file.mime_type
				FROM	file
					LEFT JOIN
					submission_box	ON	file.id = submission_box.fid
								AND
								submission_box.deleted = '0'
					LEFT JOIN
					student_submission ON	student_submission.box_id = submission_box.id 
								AND
								student_submission.owner = '".$_SESSION['id']."' 
				WHERE	file.category = '".$this->category."'
					AND
					file.type = '3'
					AND
					file.deleted = '0' 
				ORDER BY submission_box.date DESC
			";
		
		}
		else
		{
			$query = "SELECT file.id, file.description, file.comment, DATE_FORMAT(submission_box.date, '%D %b') AS date, 
			submission_box.id AS box_id, submission_box.owner, file.realname, mime_type FROM file 
			LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0' 
			WHERE file.category = '".$this->category."' AND file.type = '3' AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}

		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1 ){ $this->t_body->set_var("homework_block", "<tr><td></td><td>No Homework</td></tr>");}
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

	}
	
	function outputAssignList($name, $size)
	{
		//assignment
		$this->t_body->set_block($name, "assign", "assign_block");

		if(isStudent($_SESSION['gid']))
		{
			$query = "SELECT file.id, file.description, file.realname, file.comment, DATE_FORMAT(submission_box.date, '%D %b') AS date, 
			UNIX_TIMESTAMP(submission_box.late_date) AS late, submission_box.id AS box_id, student_submission.owner, file.mime_type
			FROM file LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0'
			LEFT JOIN student_submission ON  student_submission.box_id = submission_box.id 
			AND student_submission.owner = '".$_SESSION['id']."'
			WHERE file.category = '".$this->category."' AND file.type = '2' AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}
		else
		{
			$query = "SELECT file.id, file.description, file.comment, DATE_FORMAT(submission_box.date, '%D %b') AS date, 
			submission_box.id AS box_id, submission_box.owner, file.realname, mime_type FROM file 
			LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0' 
			WHERE file.category = '".$this->category."' AND file.type = '2' AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}

		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 


		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1 ){$this->t_body->set_var("assign_block", "<tr><td></td><td>No Assignments</td></tr>");}
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


	}
	
	function outputNoteList($name, $size)
	{
		//note output
		$this->t_body->set_block($name, "notes", "notes_block");
		
		$query = "SELECT id, description, comment, realname, mime_type FROM file 
		WHERE category = '".$this->category."' AND type = '1' AND file.deleted = '0' ORDER BY submitted DESC";

		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 
	
		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){ $array['comment'] = "No Notes Found"; $this->outputNote($array);}
		else
		{
			while($queryArray = $this->db->dbGetNextArray() )
			{
				$this->outputNote($queryArray);
			}
		}

		//setup block for submission button
		$this->t_body->set_block($name, "submit_note", "submit_note_block");
		if($this->perm < 2)		
			$this->t_body->set_var("submit_note_block", "");
		else
			$this->t_body->parse("submit_note_block", "submit_note");	

	}
	
	function outputLinkList($name, $size)
	{
		//link output
		$this->t_body->set_block($name, "link", "link_block");

		$query = "SELECT description, url, comment FROM link WHERE category = '".$this->category."' 
		ORDER BY submitted DESC";

		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){$array['comment']="No Links Found"; $this->outputLink($array);}
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

	}	
	
	function outputFileList($name, $size)
	{
		//file output
		$this->t_body->set_block($name, "files", "files_block");
	
		$query = "SELECT id, description, comment, realname, mime_type FROM file 
		WHERE category = '".$this->category."' AND file.deleted = '0' ORDER BY submitted DESC";

		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){ $array['comment'] = "No Files Found"; $this->outputFile($array);}
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
	}
		
	function outputFolderList($name, $size)
	{
		//file output
		$this->t_body->set_block($name, "folders", "folders_block");
	
		$query = "SELECT id, name FROM category 
		WHERE parent = '".$this->category."' ORDER BY name DESC";

		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){ $array['comment'] = "No Folders Available"; $this->outputFolder($array);}
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

	}
		
	function outputImageGallery($name, $size)
	{
		define("IMAGESPERROW", 4);
        
        //output thumbnails
        $this->t_body->set_block($name, "thumb_row", "row_block");
        $this->t_body->set_block("thumb_row", "thumb_cell", "cell_block");
		
		if ($size == 0)
			$lim = "";
		else
			$lim = " LIMIT 1, ".IMAGESPERROW * $size;
			
        $query = "SELECT id, description FROM file WHERE category = '{$this->category}' AND deleted = '0' AND INSTR(mime_type, 'image/')
                	ORDER BY submitted".$lim;
		$this->db->dbDoQuery($query);

        $count = 1;
        $flag = 0;
		$this->t_body->set_var("CATID", $this->category);
		if($this->db->dbCountRows() > 0 )
		{ 
	       	while( $thumbnail = $this->db->dbGetNextArray() )
        	{
				$this->t_body->set_VAR("THUMBNAIL", "<a href=\"/sendframe.php?id={$thumbnail['id']}\" target=\"_blank\">
                 <img src=\"/cms/image.php?id={$thumbnail['id']}&height=80&width=80&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\"></a>");

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
			$this->t_body->set_VAR("row_block", "There are no images in this page, <a 
			href='/cms/modResource.php?type=file&cid={$this->category}'>POST FILE</a> to upload some.");
		}
	}

	function outputForumList($name, $size)
	{	
		//forum recent posts
		$this->t_body->set_block($name, "forum", "forum_block");
	
		$query = "SELECT DISTINCT(thread) FROM forum WHERE cid='".$this->category."' AND deleted = '0' ORDER BY date DESC";
		
		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 

		$res = $this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1){ $array['heading'] = "No Forum Posts"; $this->outputForum($array);}
		else
		{
			while($curr = $this->db->dbGetNextArray($res))
			{
				$query = "SELECT id, heading FROM forum WHERE parent = '0' AND deleted ='0' AND thread = '".$curr['thread']."'";
				$this->db->dbDoQuery($query);
				$this->outputForum($this->db->dbGetNextArray());
			}
		}
	}		

	function outputEventList($name, $size)
	{
		//event output
		$this->t_body->set_block($name, "group_events", "group_events_block");
	
        	$query = "SELECT id, DATE_FORMAT(date, '%D %b') as outputDate, 
				DATE_FORMAT(date, '%Y') as year, DATE_FORMAT(date, '%c') as month, DATE_FORMAT(date, '%e') as day,
				TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, 
				priority, note 
        		  FROM event WHERE access = '2' AND sid = '".$this->category."' AND date >= NOW() ORDER BY date";

		//if no size make unlimited query else make it limited by size
		if($size != 0 && $size != '') 
			$query .= " LIMIT 0, $size"; 
		
		$this->db->dbDoQuery($query);	

		if($this->db->dbCountRows() < 1){$array['note'] = "No Events Scheduled"; $this->outputEvent($array);}
		else
		{
			require_once("calendar/calendar.php");
			$cal = new calendar;			

			while($queryArray = $this->db->dbGetNextARray() )
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
		
	
	}

	function outputHomework($queryArray)
	{
		global $allowedFileTypes;

		$url = "sendframe.php?id=".$queryArray['id'];

		//if staff or student is enrolled and there is work due provide a link
		if($this->enroled)
		{
			if(isStudent($_SESSION['gid']) && $queryArray['owner']=="" && $queryArray['late'] > date("U")) //student
				$this->t_body->set_var("HW_DATE", "<a href=\"/submission/submitWork.php?box_id=".$queryArray['box_id']."\">".$queryArray['date']."</a>");
			else if(isStaff($_SESSION['gid']) && $queryArray['owner'] == $_SESSION['id']) //if staff owns box provide link
				$this->t_body->set_var("HW_DATE", "<a href=\"/submission/viewSubmission.php?box_id=".$queryArray['box_id']."\">".$queryArray['date']."</a>");
			else
				$this->t_body->set_var("HW_DATE", $queryArray['date']);
		}
		else
			$this->t_body->set_var("HW_DATE", $queryArray['date']);

		//set name
		if($queryArray['realname'] != "")
		{
			if($allowedFileTypes[$queryArray['mime_type']][1] == 1)
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".htmlentities($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".htmlentities($queryArray['description'])."</a>";

			$this->t_body->set_var("HW_NAME", $url);
		}
		else
			$this->t_body->set_var("HW_NAME", htmlentities($queryArray['description']));
		
		$this->t_body->set_var("HW_COMMENT", nl2br(htmlentities($queryArray['comment'])));

		$this->t_body->parse("homework_block", "homework", true);

	}

	function outputAssignment($queryArray)
	{

		global $allowedFileTypes;

		//if staff or student is enrolled and there is work due provide a link
		if($this->enroled)
		{
			if(isStudent($_SESSION['gid']) && $queryArray['owner']=="" && $queryArray['late'] > date("U")) //student
				$this->t_body->set_var("ASS_DATE", "<a href=\"/submission/submitWork.php?box_id=".$queryArray['box_id']."\">".$queryArray['date']."</a>");
			else if(isStaff($_SESSION['gid']) && $queryArray['owner'] == $_SESSION['id']) //if staff owns box provide link
				$this->t_body->set_var("ASS_DATE", "<a href=\"/submission/viewSubmission.php?box_id=".$queryArray['box_id']."\">".$queryArray['date']."</a>");
			else
				$this->t_body->set_var("ASS_DATE", $queryArray['date']);
		}
		else
			$this->t_body->set_var("ASS_DATE", $queryArray['date']);

		//set name
		if($queryArray['realname'] != "")
		{
			if($allowedFileTypes[$queryArray['mime_type']][1] == 1)
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".htmlentities($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".htmlentities($queryArray['description'])."</a>";

			$this->t_body->set_var("ASS_NAME", $url);
		}
		else
			$this->t_body->set_var("ASS_NAME", htmlentities($queryArray['description']));
		
		$this->t_body->set_var("ASS_COMMENT", nl2br(htmlentities($queryArray['comment'])));

		$this->t_body->parse("assign_block", "assign", true);

	}

	function outputNote($queryArray)
	{
		global $allowedFileTypes;

		//set name
		if($queryArray['realname'] != "")
		{
			if($allowedFileTypes[$queryArray['mime_type']][1] == 1)
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".htmlentities($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".htmlentities($queryArray['description'])."</a>";

			$this->t_body->set_var("NOTE_NAME", $url);
		}
		else
			$this->t_body->set_var("NOTE_NAME", htmlentities($queryArray['description']));
	
		$this->t_body->set_var("NOTE_COMMENT", nl2br(htmlentities($queryArray['comment'])));

		$this->t_body->parse("notes_block", "notes", true);

	}

	function outputFile($queryArray)
	{
		global $allowedFileTypes;
		
		//set name
		if($queryArray['realname'] != "")
		{
			if($allowedFileTypes[$queryArray['mime_type']][1] == 1)
				$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".htmlentities($queryArray['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$queryArray['id']."\">".htmlentities($queryArray['description'])."</a>";

			$this->t_body->set_var("FILE_NAME", $url);
		}
		else
			$this->t_body->set_var("FILE_NAME", htmlentities($queryArray['description']));

		$this->t_body->set_var("FILE_COMMENT", nl2br(htmlentities($queryArray['comment'])));

		$this->t_body->parse("files_block", "files", true);

	}

	function outputFolder($queryArray)
	{
		//set name
		$this->t_body->set_var("FOLDER_NAME", "<a href=\"/browse.php?category=".$queryArray['id']."\">".htmlentities($queryArray['name'])."</a>");

		$this->t_body->parse("folders_block", "folders", true);

	}

	function outputForum($queryArray)
	{
		$url = "/forum/viewForum.php?msgid=".$queryArray['id']."&cid=".$this->category;
		
		//set name
		if($queryArray['id'] == "")
			$this->t_body->set_var("FORUM_HEAD", htmlentities($queryArray['heading']));
		else
			$this->t_body->set_var("FORUM_HEAD", "<a href=\"$url\">".htmlentities($queryArray['heading'])."</a>");

		$this->t_body->parse("forum_block", "forum", true);
	}

		

	function outputLink($queryArray)
	{
		//set the url
		include_once('include/html-parse.php');
		
		if(isLocalURL($queryArray['url']))
		{
			$this->t_body->set_var("LINK_NAME", "<a href=\"".$queryArray['url']."\">"
				.htmlentities($queryArray['description'])."</a>");
		}
		else
		{
			$this->t_body->set_var("LINK_NAME", "<a href=\"/redir.php?url=".$queryArray['url']."\" target=\"_blank\">"
				.htmlentities($queryArray['description'])."</a>");
		}
		
		//set the description
		$this->t_body->set_var("LINK_COMMENT", nl2br($queryArray['comment']));

		$this->t_body->parse("link_block", "link", true);
	}
	
	function outputEvent($queryArray)
	{
		$this->t_body->set_var("CAL_DATE", $queryArray['outputDate']);
		$this->t_body->set_var("CAL_YEAR", $queryArray['year']);
		$this->t_body->set_var("CAL_MONTH", $queryArray['month']);
		$this->t_body->set_var("CAL_DAY", $queryArray['day']);
	
                if($queryArray['startTime'] == "0:00" || $queryArray['startTime'] == "")
                        $this->t_body->set_var("CAL_TIME", "");
                else if($queryArray['endTime'] == "0:00")
                        $this->t_body->set_var("CAL_TIME", $queryArray['startTime']);
                else
                        $this->t_body->set_var("CAL_TIME", $queryArray['startTime']." - ".$queryArray['endTime']);

		$this->t_body->set_var("CAL_COLOUR",$queryArray['colour']);
		
		if($queryArray['outputDate'])
			$this->t_body->set_var("CAL_NOTE",": ".htmlentities($queryArray['note']));
		else
			$this->t_body->set_var("CAL_NOTE", htmlentities($queryArray['note']));

		$this->t_body->parse("group_events_block", "group_events", true);

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
