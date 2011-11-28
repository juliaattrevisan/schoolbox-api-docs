<?php
define('PUBLISHED_FILE', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';

$this->setBlock($componentHandle, 'files', 'files_block');
$this->setBlock('files', 'file_url', 'file_url_block');
$this->setBlock('files', 'file_no_url', 'file_no_url_block');
$this->setBlock('files', 'modify_file', 'modify_file_block');
$this->setBlock('files', 'unpublish', 'unpublish_block');

$userHandler = new User();
$fileHandler = new MyFile($userID);
$fileList = $fileHandler->getAllPublished($config);
$sortOptions = array('Description');

if (is_array($fileList)) {
	foreach ($fileList as $file) {
		$publishedDate = new Alaress_Date_Format($file['published'], 'U');

		$this->setVar(array(
            'FILE_DESCRIPTION'	=> Scrub::text($file['description']),
            'FILE_ID'			=> $file['id'],
            'FILE_MIME_IMAGE'	=> $allowedFiles->getClass($file["iconFile"]),
            'FILE_POSTED_BY'	=> $userHandler->formatName($file),
            'FILE_POSTED_BY_ID'	=> $file['owner'],
            'FILE_PUBLISHED_DATE'	=> $publishedDate->getShort(),
            'FILE_COMMENT'		=> nl2br(Scrub::text($file['comment'])),
            'ON_OFF'			=> ++$j%2 ? '' : 'dark',
            'HIDDEN'            => $file['hidden'] ? 'hidden' : '',
        ));

        if ($file['published'] != '') {
            $this->parse('unpublish_block', 'unpublish');
        }
        else {
            $this->parse('unpublish_block', '');
        }

		/* Set name
         */
		if ($file['realname'] != "") {
			if ($file['inline'] == 'true' && FILE_use_frame) {
				$this->setVar('FILE_URL', '/sendframe.php?myFile=1&published=' . PUBLISHED_FILE . '&id=' . $file['id']);
				$this->setVar('FILE_TARGET', '_blank');
			}
			else {
				$this->setVar('FILE_URL', '/send.php?myFile=1&published=' . PUBLISHED_FILE . '&id=' . $file['id']);
				$this->setVar('FILE_TARGET', '_blank');
			}

			$this->setVar('file_no_url_block', '');
			$this->parse('file_url_block', 'file_url');
		}
		else {
			$this->setVar('file_url_block', '');
			$this->parse('file_no_url_block', 'file_no_url');
		}

		$this->parse('files_block', 'files', true);
	}
}
else {
	$this->setVar('files_block', '');
}

/* Filter config
 */
$config['filter'] = is_array($config['filter']) ? $config['filter'] : array($config['filter']);

$this->setBlock("config", "filter", "filter_block".$componentCount);

foreach ($default['filterOptions'] as $option) {
    $this->setVar(array(
        'COMPONENT_CONFIG_FILTER_VALUE' 	=> $option,
        'COMPONENT_CONFIG_FILTER_NAME' 		=> ucwords($option),
        'COMPONENT_CONFIG_FILTER_SELECTED' 	=> in_array($option, $config['filter']) ? 'checked="checked"' : '',
    ));

    $this->parse("filter_block".$componentCount, "filter", true);
}
?>
