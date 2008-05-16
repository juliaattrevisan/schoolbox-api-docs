<?php

require_once 'include/permission.php';

if (is_numeric($this->arg[1])) {
	switch ($this->arg[0]) {
		case "cat-list":
			if($this->arg[1] > 0) {
				$openPerm = perm($this->arg[1], 1);
			}	
			list($categoryHTML) = getCategoryHTML($this->arg[1]);
			print $categoryHTML;
			break;
		case "file-list":
			if($this->arg[1] > 0) {
				$openPerm = perm($this->arg[1], 1);
			}	
			$fileHTML = getFileHTML($this->arg[1]);
			print $fileHTML;
			break;	
		case "save-history":
			$_SESSION['smCategory'] = (int)$this->arg[1];
			break;	
		case "details":
			$fileDetails = outputDetails((int)$this->arg[1]);
			print $fileDetails;
			break;						
	}
	exit;
}

$this->setFile(TEMPLATE_content, 'sitemap/sitemap.html');
$this->setTab("resources");
$this->setVar("TITLE", HEADING." - Sitemap");

$categoryHandler = new Category;

$categoryID = isset($this->arg[0]) ? $this->arg[0] : $_SESSION['smCategory'];
$categoryID = $_SESSION['smCategory'] = is_numeric($categoryID) ? $categoryID : 0;

$this->setVar("CID", $categoryID);

// build categoryID array
$categoryIDList = array ($categoryID);
$parent = $categoryID;
while ($parent > 0) {
	
	$query = "SELECT parent FROM category WHERE id = $parent";
	$category = $this->db->getRow($query);
	$parent = $category['parent'];
	array_push($categoryIDList, (int)$parent);
} 

// left column
list($categoryHTML) = getCategoryHTML($categoryIDList);
$this->setVar('NAV_CONTENT', $categoryHTML);


// right column
$windowHTML = "";
if ($categoryID > 0) {
	$openPerm = perm($categoryID, 1);
	list( , $windowHTML) = getCategoryHTML($categoryID);
}
$windowHTML .= getFileHTML($categoryID);	
$this->setVar('WINDOW_CONTENT', '<div id="window_'.$categoryID.'">' . $windowHTML . '</div>');		



// functions
function getCategoryHTML($categoryIDList) {
	
	
	$page = new SitePage();
	$page->setFile('blockBODY', 'sitemap/sitemap_block.html');

	$page->setBlock('blockBODY', 'category', 'category_block');
	$page->setBlock('blockBODY', 'category_window', 'category_window_block');


	if (!is_array($categoryIDList)) {
		$categoryIDList = array($categoryIDList);
	}
	
	for ($i = 0; $i < count($categoryIDList); $i++) {
		
		$query = "SELECT id, name, code, template,
				  (SELECT count(*) FROM category WHERE parent = c.id) as children
				  FROM category c
				  WHERE parent = ".$categoryIDList[$i]." ORDER BY name";	
		$catList = $page->db->getAll($query);

		$page->setVar('category_block', '');
		$page->setVar('category_window_block', '');

		if (is_array($catList)) {
			foreach($catList as $category) {	
				
				if (perm($category['id'], 1) < 1) continue;
				
				$output = array('CAT_ID' 		=> $category['id'],
								'CAT_NAME'      => $category['name'] . (!empty($category['code']) ? " (" . $category['code'] . ")":""),
								'CAT_OPENCLOSE' => $category['children'] > 0 ? "<img src=\"/images/icons/sitemap-" . 
												 ( $category['id'] == $previousCat ? "collapse" : "expand") . ".gif\" alt=\"Expand\" />":"",
								'SUB_CATEGORY'  => ($i > 0 && $category['id'] == $previousCat)? '<div id="category_'.$category['id'].'">' . 
																							  $previousCatHTML .'</div>' : '',
								'CAT_MARGIN'    => ($i+1<count($categoryIDList)? "style='margin-left: 30px;'" : "") ,
								'CAT_SELECTED'  => $category['id'] == $_SESSION['smCategory'] ? "id='sitemap-selected'" : "",
								'HOMEPAGE'	  	=> $category['template'] > 0 ? "Y" : "N",
								);
								
				$page->setVar($output);

				$page->parse('category_block', 'category', true);
				$page->parse('category_window_block', 'category_window', true);
								
				
			}
			$previousCat = $categoryIDList[$i];
			$previousCatHTML = $page->getVar('category_block');
		} 
	}
	
	return array($page->getVar('category_block'), $page->getVar('category_window_block'));
}

function getFileHTML($categoryID) {

	$page = new SitePage();
	$page->setFile('blockBODY', 'sitemap/sitemap_block.html');
	$page->setBlock('blockBODY', 'file', 'file_block');
	
	global $openPerm;
	
	$query = "SELECT file.id, realname, '' as url, file_size, file.description, iconFile, 
					allowedFileTypes.description as mime_description, inline, hidden, 'N' is_url
			  FROM file 
			  LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
			  WHERE file.category = " . $categoryID . " AND deleted = '0'
			  
			  UNION SELECT link.id, '', link.url, '', link.description, 'http-url.png', link.comment, '', hidden, 'Y' is_url
			  FROM link
			  WHERE link.category = " . $categoryID . "
			  
			  ORDER BY description";
	$fileList = $page->db->getAll($query);
	
	if (is_array($fileList)) {
		foreach ($fileList as $file) {
			
			if ($file['hidden'] > 0 && $openPerm < 2) continue;
		
			$output = array('FILE_ID' 	 	=> $file['id'],
							'FILE_NAME' 	=> scrubText($file['description']),
							'FILE_ICON' 	=> !is_null($file['iconFile']) ? "/images/mime/".$file['iconFile'] : "/images/mime/application-x-none.png",
							'FILE_ALT'  	=> !is_null($file['mime_description']) ? $file['mime_description'] : "File",
							'CAT_ID'	 	=> $parent,
							'FILE_INLINE' 	=> $file['inline']=='true'?"Y":"N",
							'URL_YN'	 	=> $file['is_url'],
							'HIDDEN'	 	=> $file['hidden'] > 0 ? "style='opacity: 0.55;'" : "",
							'FILE_LINK' 	=> urlencode($file['url']),
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
					$page->setVar("FILE_SIZE", "");
					$file['realname'] = "";	
				}
				else
					$page->setVar("FILE_SIZE", bytesToString($filesize));
			}
			else
				$page->setVar("FILE_SIZE", "");	
				

			if($file['realname'] != "")
			{
				//if inline open in new window, if not give user a save dialog box
				if($file['inline'] == 'true')
					$page->setVar("FILE_URL", "<a href=\"/sendframe.php?id=".$file['id']."\" onclick=\"window.open(this.href, '_blank',''); return false;\">");
				else
					$page->setVar("FILE_URL", "<a href=\"/send.php?id=".$file['id']."&save=1\">");
					
				$page->setVar('FILE_URL_END', '</a>');
			
			} else if(isLocalURL($file['url']))
			{
				$page->setVar("FILE_URL", "<a href=\"" . $file['url'] ."\" onclick=\"window.open(this.href, '_self',''); return false;\">");
				$page->setVar('FILE_URL_END', '</a>');
			}
			else if ($file['url'] != "")
			{
				$page->setVar("FILE_URL", "<a href=\"/redir.php?url=" . $file['url'] ."\" onclick=\"window.open(this.href, '_blank',''); return false;\">");
				$page->setVar('FILE_URL_END', '</a>');
			}
			
			else
			{
				$page->setVar('FILE_URL', '');
				$page->setVar('FILE_URL_END', '');
			}										
							
			$page->setVar($output);
			$page->parse('file_block', 'file', true);

		}
	} else
		$page->setVar('file_block', '');	
		
	return $page->getVar('file_block');

}

function outputDetails ($fileID) {

	$page = new SitePage();
	$page->setFile('blockBODY', 'sitemap/sitemap_block.html');
	$page->setBlock('blockBODY', 'file_details', 'file_details_block');

	$query = "SELECT f.comment, UNIX_TIMESTAMP(f.submitted) submitted, concat(substring(u.firstname,1,1),' ',u.lastname) owner
			  FROM file f
			  LEFT JOIN user u ON u.id = f.id 
			  WHERE f.id = $fileID";
	$file = $page->db->getRow($query);
	
	$output = array ('COMMENT'	=> $file['comment'],
					 'CREATED'	=> date("M j y @ g:iA", $file['submitted']),
					 'OWNER'		=> $file['owner'],
					 );
	$page->setVar($output);
	
	$page->parse('file_details_block', 'file_details');

	return $page->getVar('file_details_block');
	
}

?>