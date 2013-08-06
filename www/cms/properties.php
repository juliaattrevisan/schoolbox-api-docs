<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage;
$fileHandler = new File;
$linkHandler = new Link;
$groupHandler = new Group;

$page->setFile(TEMPLATE_body, 'cms/properties.html');
$page->setBlock(TEMPLATE_body, 'realname', 'realname_block');
$page->setBlock(TEMPLATE_body, 'link', 'link_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');

if (is_numeric($_GET['fileID'])) {

	$file = $fileHandler->getByID($_GET['fileID']);

	if ($groupHandler->getPermission($file['category']) < READ_ACCESS)
		error(19);

	if ($groupHandler->getPermission($file['category']) < WRITE_ACCESS && $file['hidden'] > 0)
		error(19);

	$output = array ('FILE_ID'	 => $file['id'],
					 'CATEGORY'	 => $file['categoryName'],
					 'OWNER'	 => $file['ownerName'],
					 'OWNER_ID'	 => $file['owner'],
					 'REALNAME'	 => $file['realname'],
					 'CREATED'	 => date(Cfg::get('date_short'), $file['submitted']),
					 'DESCRIPTION'	=> Scrub::text($file['description']),
					 'COMMENT'	 => Scrub::text($file['comment']),
					 'SIZE'		 => number_format($file['file_size']/1024, 1),
					 'LINK'		=> '/send.php?id='.$file['id'],
					 'HOST'         => SitePage::conType().$_SERVER['HTTP_HOST'],
					 'MIME_TYPE' => $file['mime_type'],
					 'HIDDEN'	 => $file['hidden'] > 0 ? 'Yes' : 'No',
					 );
	$page->setVar($output);
	$page->parse('realname_block', 'realname');
	$page->setVar('link_block', '');
	$page->parse('file_block', 'file');



	$page->outputPage(true);

}
elseif (is_numeric($_GET['linkID'])) {
	$link = $linkHandler->getByID($_GET['linkID']);

	if ($groupHandler->getPermission($link['category']) < READ_ACCESS)
		error(19);

	if ($groupHandler->getPermission($link['category']) < WRITE_ACCESS && $link['hidden'] > 0)
		error(19);

	$output = array ('FILE_ID'	 => $link['id'],
					 'CATEGORY'	 => $link['categoryName'],
					 'OWNER'	 => $link['ownerName'],
					 'OWNER_ID'	 => $link['owner'],
					 'URL'	 	 => $link['url'],
					 'CREATED'	 => date(Cfg::get('date_short'), $link['submitted']),
					 'DESCRIPTION'	=> Scrub::text($link['description']),
					 'COMMENT'	 => Scrub::text($link['comment']),
					 'LINK'		=> $link['url'],
					 'HOST'		 => '',
					 'HIDDEN'	 => $link['hidden'] > 0 ? 'Yes' : 'No',
					 );

	$page->setVar($output);
	$page->setVar('realname_block', '');
	$page->setVar('file_block', '');
	$page->parse('link_block', 'link');

	$page->outputPage(true);

} else
	error(2);

?>