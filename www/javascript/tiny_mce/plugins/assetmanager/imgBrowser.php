<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

$page = new SitePage;
$page->setFile(TEMPLATE_body, 'assetmanager/imgBrowser.htm');
$page->setBlock(TEMPLATE_body, 'image_row', 'image_row_block');

$output = array('CATEGORY_ID'	=> $_GET['categoryID'],
				);
$page->setVar($output);

$default = isset($_GET['default']) ? (int)$_GET['default'] : 0;

$fileHandler = new File;
$fileList = $fileHandler->getAllInCategory($_GET['categoryID'], array('filter' => 'image'));

if (is_array($fileList)) {
	foreach ($fileList as $file) {
		
		list($width, $height) = getimagesize(FILE_directory . '/' . $file['id'] . '.dat');
		$wr = 100 / $width;
		$hr = 100 / $height;
		
		if ($width > $height) {
			$wr = 100 / $width;
			$hr = $wr;
		} else {
			$wr = 100 / $height;
			$hr = $wr;
		}
		
		$width  = round($width * $wr);
		$height = round($height * $hr);
		
		$output = array ( 'FILE_ID' => $file['id'],
						  'TITLE' => $file['description'],
						  'TITLE_SHORT' => substr($file['description'], 0, 17) . (strlen($file['description']) > 17?'..':''),
						  'DESC'  => $file['description'],
						  'FNAME' => $file['realname'],
						  'URL'   => "http://" . $_SERVER['HTTP_HOST'] . "/cms/image.php?id=". $file['id']."&height=550&width=600&resample=0",
						  'WIDTH' => $width,
						  'HEIGHT' => $height,
						  'SIZE'  => round($file['file_size']/1024),
						  'MIME'  => $file['mime_type'],
						  'DATE'  => $file['submitted'],
						  'SELECTED' => $file['id']==$default?"id='assetmanager_current'":"",
						);
		$page->setVar($output);
		$page->parse('image_row_block', 'image_row', true);
		
	}
} else
	$page->setVar('image_row_block', '');

$page->outputPage(true);
?>