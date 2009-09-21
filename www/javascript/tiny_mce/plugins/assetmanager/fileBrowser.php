<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/adminPage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/asset.php';

$adminPage = new AdminPage;
$adminPage->template->set_file('contentTpl', $_SERVER['DOCUMENT_ROOT'] . '/inc/tiny_mce/plugins/assetmanager/fileBrowser.htm');
$adminPage->setBlock('contentTpl', 'file_row', 'file_row_block');

$default = isset($_GET['default']) ? (int)$_GET['default'] : 0;

$assetHandler = new Asset;
$assetList = $assetHandler->getAll();
if (is_array($assetList)) {
	foreach ($assetList as $asset) {
	
		$output = array ( 'FILE_ID' => $asset['fileID'],
						  'TITLE' => $asset['title'],
						  'TITLE_SHORT' => substr($asset['title'], 0, 17) . (strlen($asset['title']) > 17?'..':''),
						  'DESC'  => $asset['description'],
						  'FNAME' => $asset['filename'],
						  'URL'   => "http://" . $_SERVER['HTTP_HOST'] . "/document/send/". $asset['fileID'],
						  'WIDTH' => $width,
						  'HEIGHT' => $height,
						  'SIZE'  => round($asset['filesize']/1024),
						  'MIME'  => $asset['mime'],
						  'DATE'  => date("d.M.Y", $asset['subtime']),
						  'SELECTED' => $asset['fileID']==$default?"id='assetmanager_current'":"",
						);
		$adminPage->setVar($output);
		$adminPage->parse('file_row_block', 'file_row', true);
	}
} else
	$adminPage->setVar('file_row_block', '');
$adminPage->outputPage();
?>