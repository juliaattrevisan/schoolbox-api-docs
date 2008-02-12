<?php

require_once('include/session.php');
require_once('include/include.php');
require_once('include/permission.php');

$db = new dbManager;
$t_body = new Template(TPL_directory);


class OutPut
{
	function OutPut($category, $verbose)
	{
	
		$this->category = $category;
		$this->verbose = $verbose;
		
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file('tplBODY', 'sitemap.htm');

		$this->t_body->set_var("TITLE", HEADING." - Sitemap");
		$this->t_body->set_var("CID", $category);
		

		//database handle
		$this->db = new dbManager;

		include_once("include/page.php");	

	}

	function showPage($catID)
	{
		
		$this->t_body->set_var('NAV_CONTENT', '');
		$this->t_body->set_var('WINDOW_CONTENT', '');	
	
		$this->t_body->set_file('blockBODY', 'sitemap_block.htm');

		$this->t_body->set_block('blockBODY', 'file', 'file_block');		
		$this->t_body->set_block('blockBODY', 'category', 'category_block');
		$this->t_body->set_block('blockBODY', 'category_window', 'category_window_block');

		$catTree = array ($catID);
		$parent = $catID;
		while ($parent > 0) {
			$query = "SELECT parent FROM category WHERE id = $parent";
			$category = $this->db->dbGetSingleRow($query);
			$parent = $category['parent'];
			array_push($catTree, $parent);
		} 
		
		$this->outputCategories($catTree, 'nav');
		$this->t_body->set_var('NAV_CONTENT', $this->t_body->varvals['category_block']);
		if ($catID > 0) {
			$this->outputCategories($catID, 'window');
		}
		$this->outputFiles($catID);		
		$this->t_body->set_var('WINDOW_CONTENT', '<div id="window_'.$catID.'">' . $this->t_body->varvals['category_window_block'] . $this->t_body->varvals['file_block'] . '</div>');		

		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	function outputCategories($catTree, $block)
	{

		if (!is_array($catTree)) {
			$catTree = array($catTree);
		}
		
		for ($i=0;$i<count($catTree); $i++) {
			
			$this->t_body->set_var('category_block', '');
			
			$query = "SELECT 
						id, 
						name,
						code,
					  (SELECT count(*) FROM category WHERE parent = c.id) + (SELECT count(*) FROM file WHERE category = c.id) as children,
					  p.type as perm
					  FROM category c
					  LEFT JOIN perms p on c.id = p.cid AND gid = " . $_SESSION['gid'] . "
					  WHERE parent = ".$catTree[$i]." ORDER BY name";	
			$catList = $this->db->dbGetAllRows($query);
	
			$this->t_body->set_var('category_block', '');
			$this->t_body->set_var('category_window_block', '');
	
			if (is_array($catList)) {
				foreach($catList as $category) {	
					
					if ($category['perm'] < 1) continue;
					
					$output = array ('CAT_ID' 		 => $category['id'],
									 'CAT_NAME'      => $category['name'] . (!empty($category['code']) ? " (" . $category['code'] . ")":""),
									 'CAT_OPENCLOSE' => $category['children']>0?"<img src=\"/images/icons/sitemap-" . 
									 					( $category['id'] == $previousCat? "collapse" : "expand") . ".gif\" alt=\"Expand\" />":"",
									 'SUB_CATEGORY'  => ($i > 0 && $category['id'] == $previousCat)? '<div id="category_'.$category['id'].'">' . $previousCatHTML .'</div>' : '',
									 'CAT_MARGIN'    => $i+1<count($catTree)? "style='margin-left: 30px;'" : "",
									 'CAT_SELECTED'  => $category['id']==$_REQUEST['category'] ? "id='sitemap-selected'" : "",
	
									);
									
					$this->t_body->set_var($output);
					
					
					if ($block == 'nav') {
						
						$this->t_body->parse('category_block', 'category', true);
						
					} else {
						$this->t_body->parse('category_window_block', 'category_window', true);
					}
					

				}
				$previousCat = $catTree[$i];
				$previousCatHTML = $this->t_body->get_var('category_block');
				
				
//				var_dump($previousCatHTML);
				
			} 
			
		}
	}
	
	function outputFiles($parent)
	{
		
		$query = "SELECT file.id, realname, '' as url, file_size, file.description, iconFile, allowedFileTypes.description as mime_description, inline, hidden
				  FROM file 
				  LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
				  WHERE file.category = $parent AND deleted = '0'
				  
				  UNION SELECT link.id, '', link.url, '', link.description, 'http-url.png', link.comment, '', hidden
				  FROM link
				  WHERE link.category = $parent
				  
				  ORDER BY description";
		$fileList = $this->db->dbGetAllRows($query);
		
		if (is_array($fileList)) {
			foreach ($fileList as $file) {
				
				if ($file['hidden'] > 0 && $this->perm < 2) continue;
			
				$output = array ('FILE_ID' 	 => $file['id'],
								 'FILE_NAME' => scrubText($file['description']),
								 'FILE_ICON' => !is_null($file['iconFile']) ? "/images/mime/".$file['iconFile'] : "/images/mime/application-x-none.png",
								 'FILE_ALT'  => !is_null($file['mime_description']) ? $file['mime_description'] : "File",
								 'CAT_ID'	 => $parent,
								 'HIDDEN'	 => $file['hidden'] > 0 ? "style='opacity: 0.55;'" : "",
	
								);

				$filename = FILE_directory . "/" . $file['id'] . ".dat";

				// set file size
				if($file['realname'] != "")
				{
					if($file['file_size'])
						$filesize = $file['file_size'];
					else
						$filesize = @filesize($filename);
					
					if($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
					{
						$this->t_body->set_var("FILE_SIZE", "");
						$file['realname'] = "";	
					}
					else
						$this->t_body->set_var("FILE_SIZE", bytesToString($filesize));
				}
				else
					$this->t_body->set_var("FILE_SIZE", "");	
					

				if($file['realname'] != "")
				{
					//if inline open in new window, if not give user a save dialog box
					if($file['inline'] == 'true')
						$this->t_body->set_var("FILE_URL", "<a href=\"/sendframe.php?id=".$file['id']."\" onclick=\"window.open(this.href, '_blank',''); return false;\">");
					else
						$this->t_body->set_var("FILE_URL", "<a href=\"/send.php?id=".$file['id']."&save=1\">");
						
					$this->t_body->set_var('FILE_URL_END', '</a>');
				
				} else if(isLocalURL($file['url']))
				{
					$this->t_body->set_var("FILE_URL", "<a href=\"" . $file['url'] ."\" onclick=\"window.open(this.href, '_self',''); return false;\">");
					$this->t_body->set_var('FILE_URL_END', '</a>');
				}
				else if ($file['url'] != "")
				{
					$this->t_body->set_var("FILE_URL", "<a href=\"/redir.php?url=" . $file['url'] ."\" onclick=\"window.open(this.href, '_blank',''); return false;\">");
					$this->t_body->set_var('FILE_URL_END', '</a>');
				}
				
				else
				{
					$this->t_body->set_var('FILE_URL', '');
					$this->t_body->set_var('FILE_URL_END', '');
				}										
								
				$this->t_body->set_var($output);
				$this->t_body->parse('file_block', 'file', true);
	
			}
		} else
			$this->t_body->set_var('file_block', '');	
	
	}


	function outputNav($parent) {

		if($parent > 0) {
			$this->perm = perm($parent, 1);
		}	

		$this->t_body->set_file('blockBODY', 'sitemap_block.htm');
		$this->t_body->set_block('blockBODY', 'category', 'category_block');
		$this->t_body->set_block('blockBODY', 'file', 'file_block');
		
		$this->t_body->set_block('blockBODY', 'category_window', 'category_window_block');
		
		$this->t_body->set_var('category_window_block', '');
		$this->t_body->set_var('file_block', '');
		
		$this->outputCategories($parent, 'nav');
		
		$this->t_body->pparse("finaloutput", "blockBODY");
		
	}
	
	function outputWindow($parent) {

		if($parent > 0) {
			$this->perm = perm($parent, 1);
		}		
	
		$this->t_body->set_file('blockBODY', 'sitemap_block.htm');
		$this->t_body->set_block('blockBODY', 'category', 'category_block');
		$this->t_body->set_block('blockBODY', 'file', 'file_block');
		
		$this->t_body->set_block('blockBODY', 'category_window', 'category_window_block');

		$this->t_body->set_var('category_block', '');
		$this->t_body->set_var('file_block', '');
		
		$this->outputCategories($parent, 'window');
		$this->outputFiles($parent);
		
		$this->t_body->pparse("finaloutput", "blockBODY");	
	}
}


$myPage = new OutPut((int)$_GET['category'], (int)$_GET['verbose']);


if (isset($_GET['catList'])) {
	$parent = (int)$_GET['catList'];
	$myPage->outputNav($parent);
	exit;
} else if (isset($_GET['fileList'])) {
	$parent = (int)$_GET['fileList'];
	$myPage->outputWindow($parent);
	exit;
}

$myPage->showPage((int)$_REQUEST['category']);

?>