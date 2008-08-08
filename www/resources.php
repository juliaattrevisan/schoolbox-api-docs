<?php

if (!$this) {
	require_once '../include/sitePage.php';
	$page = new SitePage(false);
	session_start();
}
if ( !isset($_SESSION['id'])) {
	print "<script type=\"text/javascript\">window.open('/resources/','_self','');</script>";
	exit;
}

if (is_numeric($_GET['cat-list'])) {

	list($categoryHTML) = getCategoryHTML($_GET['cat-list']);
	print $categoryHTML;
	exit;

} else if (is_numeric($_GET['file-list'])) {

	list(, $windowHTML) = getCategoryHTML($_GET['file-list']);			
	$windowHTML .= getFileHTML($_GET['file-list']);
	print $windowHTML;
	exit;

} else if (is_numeric($_GET['save-history'])) {

	$_SESSION['smCategory'] = (int)$_GET['save-history'];
	exit;

} else if (is_numeric($_GET['details'])) {
	
	$fileDetails = outputDetails((int)$_GET['details']);
	print $fileDetails;
	exit;
} else if (is_numeric($_GET['link-details'])) {
	
	$linkDetails = outputLinkDetails((int)$_GET['link-details']);
	print $linkDetails;
	exit;
}


$this->setFile(TEMPLATE_content, 'resources/resources.html');
$this->setTab("resources");
$this->setTitle('Resources');

$categoryID = is_numeric($this->arg[0]) ? $this->arg[0] : $_SESSION['smCategory'];
$categoryID = $_SESSION['smCategory'] = is_numeric($categoryID) ? $categoryID : 0;

$this->setVar("CID", $categoryID);

require_once '../library/category.php';
$categoryHandler = new Category;

$this->setVar("SITE", $this->site);

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
	list( , $windowHTML) = getCategoryHTML($categoryID);
}
$windowHTML .= getFileHTML($categoryID);	
$this->setVar('WINDOW_CONTENT', '<div id="window_'.$categoryID.'">' . $windowHTML . '</div>');		



// functions
function getCategoryHTML($categoryIDList) {
	
	
	$page = new SitePage();
	$page->setFile('blockBODY', 'resources/resources_block.html');

	$page->setBlock('blockBODY', 'category', 'category_block');
	$page->setBlock('blockBODY', 'category_window', 'category_window_block');


	if (!is_array($categoryIDList)) {
		$categoryIDList = array($categoryIDList);
	}
	
	require_once '../library/category.php';
	$categoryHandler = new Category;	
		
	for ($i = 0; $i < count($categoryIDList); $i++) {


		$catList = $categoryHandler->getCategoryList($categoryIDList[$i]);
		$catPerm = perm($categoryIDList[$i], 1);
		if ($catPerm < 1 && $categoryIDList[$i]['parent'] > 0)
			continue;
		
		$page->setVar('category_block', '');
		$page->setVar('category_window_block', '');

		if (is_array($catList)) {
			foreach($catList as $category) {	
				
				$subPerm = perm($category['id'], 1);
				if ($subPerm < 1) continue;
				
				$category['children'] = $categoryHandler->hasChildren($category['id']);
				
				$output = array('CAT_ID' 		=> $category['id'],
								'CAT_NAME'      => $category['name'] . (!empty($category['code']) ? " (" . $category['code'] . ")":""),
								'CAT_OPENCLOSE' => $category['children'] > 0 ? "<img src=\"/images/" . 
												 ( $category['id'] == $previousCat ? "expanded" : "collapsed") . ".gif\" alt=\"Expand\" />":"",
								'SUB_CATEGORY'  => ($i > 0 && $category['id'] == $previousCat)? '<div id="category_'.$category['id'].'">' . 
																							  $previousCatHTML .'</div>' : '',
								'CAT_MARGIN'    => ($i+1<count($categoryIDList)? "style='margin-left: 30px;'" : "") ,
								'CAT_SELECTED'  => $category['id'] == $_SESSION['smCategory'] ? "id='resources-selected'" : "",
								'HOMEPAGE'	  	=> $category['template'] > 0 ? "Y" : "N",
								'HAS_PERM'		=> $subPerm > 1 ? 'Y' : '',
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
	$page->setFile('blockBODY', 'resources/resources_block.html');
	$page->setBlock('blockBODY', 'file', 'file_block');
	
	require_once '../library/file.php';
	require_once '../library/link.php';
	$fileHandler = new File;
	$linkHandler = new Link;		
	
	$fileList = $fileHandler->getAllInCategory($categoryID);
	$linkList = $linkHandler->getAllInCategory($categoryID);
	
	$catPerm = perm($categoryID, 1);
	if ($catPerm < 1 && $categoryID > 0) {
		unset($_SESSION['smCategory']);
		error(14);
	}

	if (is_array($linkList)) {
		$fileList = is_array($fileList) ? $fileList : array();
		$fileList = array_merge($fileList, $linkList);
	
		foreach ($fileList as $row) {
			$arrayOut[$row['description']]  = $row;
		}
		ksort($arrayOut);
		$fileList =& $arrayOut;
	}
	
	if (is_array($fileList)) {
	
		foreach ($fileList as $file) {
			
			if ($file['hidden'] > 0 && $catPerm < 2) continue;
		
			$output = array('FILE_ID' 	 	=> $file['id'],
							'FILE_NAME' 	=> scrubText($file['description']),
							'FILE_ICON' 	=> !is_null($file['iconFile']) ? "/images/mime/".$file['iconFile'] : "/images/mime/application-x-none.png",
							'FILE_ALT'  	=> !is_null($file['mime_description']) ? $file['mime_description'] : $file['comment'],
							'CAT_ID'	 	=> $file['category'],
							'FILE_INLINE' 	=> $file['inline']=='true'?"Y":"N",
							'URL_YN'	 	=> $file['url'] ? "Y":"N",
							'HIDDEN'	 	=> $file['hidden'] > 0 ? "style='opacity: 0.55;'" : "",
							'FILE_LINK' 	=> urlencode($file['url']),
							'HAS_PERM'		=> $catPerm > 1 ? 'Y' : '',
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
				$page->setVar("FILE_URL", "<a href=\"/redir.php?url=" . urlencode(urlencode($file['url'])) ."\" title=\"" . $file['url'] . "\" onclick=\"window.open(this.href, '_blank',''); return false;\">");
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
	$page->setFile('blockBODY', 'resources/resources_block.html');
	$page->setBlock('blockBODY', 'file_details', 'file_details_block');

	$file = $fileHandler->getByID($fileID);

	$output = array ('FILE_ID'	 => $file['id'],
					 'CATEGORY'	 => $file['categoryName'],
					 'OWNER'	 => $file['ownerName'],
					 'OWNER_ID'	 => $file['owner'],
					 'REALNAME'	 => $file['realname'],
					 'CREATED'	 => date("M j y @ g:iA", $file['submitted']),
					 'DESCRIPTION'	=> scrubText($file['description']),
					 'COMMENT'	 => scrubText($file['comment']),
					 'SIZE'		 => number_format($file['file_size']/1024, 1),
					 'MIME_TYPE' => $file['mime_type'],
					 'HIDDEN'	 => $file['hidden'] > 0 ? 'Yes' : 'No',
					 );
	
	
	$page->setVar($output);
	
	$page->parse('file_details_block', 'file_details');

	return $page->getVar('file_details_block');
	
}

function outputLinkDetails ($linkID) {

	require_once '../library/link.php';
	$linkHandler = new Link;

	$page = new SitePage();
	$page->setFile('blockBODY', 'resources/resources_block.html');
	$page->setBlock('blockBODY', 'link_details', 'link_details_block');

	$link = $linkHandler->getByID($linkID);

	$output = array ('FILE_ID'	 => $link['id'],
					 'CATEGORY'	 => $link['categoryName'],
					 'OWNER'	 => $link['ownerName'],
					 'OWNER_ID'	 => $link['owner'],
					 'URL'	 	 => $link['url'],
					 'CREATED'	 => date("M j y @ g:iA", $link['submitted']),
					 'DESCRIPTION'	=> scrubText($link['description']),
					 'COMMENT'	 => scrubText($link['comment']),
					 'HIDDEN'	 => $link['hidden'] > 0 ? 'Yes' : 'No',
					 );
	
	
	$page->setVar($output);
	
	$page->parse('link_details_block', 'link_details');

	return $page->getVar('link_details_block');
	
}


?>