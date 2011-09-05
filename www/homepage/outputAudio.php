<?php

$this->setJavascript('/javascript/swfobject.js');
$this->setBlock($componentHandle, 'audio_list', 'audio_list_block');

require_once SITE_root. '/library/file.php';
$fileHandler = new File;

$config['filter'] = array('audio/mpeg', 'audio/mpa', 'audio/aac', 'audio/mp4', 'audio/wav', 'audio/wave', 'audio/x-wav');

$fileList = $fileHandler->getAllInCategory($categoryID, $config);
if (is_array($fileList)) {
	foreach ($fileList as $file) {
		$output = array('AUDIO_MIME_IMAGE'	=> $allowedFiles->getClass($file['iconFile']),
						'AUDIO_URL'			=> '/send.php?id=' . $file['id'],
						'AUDIO_DESCRIPTION'	=> Scrub::text($file['description']),
						'AUDIO_COMMENT'		=> Scrub::text($file['comment']),
						'AUDIO_ID'			=> $file['id'],
						'ON_OFF'			=> ++$j%2 ? '' : 'dark',
						);

		$this->setVar($output);
		$this->parse('audio_list_block', 'audio_list', true);
	}
} else
	$this->setVar('audio_list_block', '');


if (!empty($config['url']))
	$this->setVar('AUDIO_URL', !empty($config['url']) ? $config['url'] : '/homepage/playlist.php?categoryID=' . $categoryID);




//setup block for submission button
$this->setBlock($componentHandle, "submit_file", "submit_file_block");
if ($permission < WRITE_ACCESS)
	$this->setVar("submit_file_block", "");
else
	$this->parse("submit_file_block", "submit_file");

?>