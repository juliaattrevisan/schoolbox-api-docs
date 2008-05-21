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

$categoryID = isset($this->arg[0]) ? $this->arg[0] : $_SESSION['smCategory'];
$categoryID = $_SESSION['smCategory'] = is_numeric($categoryID) ? $categoryID : 0;

$this->setVar("CID", $categoryID);

require_once '../library/category.php';
$categoryHandler = new Category;

// build categoryID array
$categoryIDList = array ($categoryID);
$parent = $categoryID;
while ($parent > 0) {
	
	$parent = $categoryHandler->getParent($parent);
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
	
	require_once '../library/category.php';
	$categoryHandler = new Category;	
	
	for ($i = 0; $i < count($categoryIDList); $i++) {


		$catList = $categoryHandler->getCategoryList($categoryIDList[$i]);

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

	require_once '../library/file.php';
	$fileHandler = new File;		
	
	$fileList = $fileHandler->getAllInCategory($categoryID);
	
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

	require_once '../library/file.php';
	$fileHandler = new File;

	$page = new SitePage();
	$page->setFile('blockBODY', 'sitemap/sitemap_block.html');
	$page->setBlock('blockBODY', 'file_details', 'file_details_block');

	$file = $fileHandler->getByID($fileID);
	
	$output = array ('FILE_ID'	=> $file['id'],
					 'CATEGORY'	=> $file['categoryName'],
					 'OWNER'	=> $file['ownerName'],
					 'REALNAME'	=> $file['realname'],
					 'OWNER'	=> $file['ownerName'],
					 'CREATED'	=> date("M j y @ g:iA", $file['submitted']),
					 'DESCRIPTION'	=> scrubText($file['description']),
					 'COMMENT'	=> scrubText($file['comment']),
					 'TYPE'		=> $file['typName'],
					 'SIZE'		=> $file['file_size'],
					 'MIME_TYPE'	=> $file['miem_type'],
					 'HIDDEN'	=> $file['hidden'] > 0 ? 'Yes' : 'No',
					 'OWNER'	=> $file['ownerName'],
					 );
	$page->setVar($output);
	
	$page->parse('file_details_block', 'file_details');

	return $page->getVar('file_details_block');
	
}

?>