<?php
//file output
$this->setBlock($componentHandle, 'files', 'files_block');
$this->setBlock('files', 'file_url', 'file_url_block');
$this->setBlock('files', 'file_no_url', 'file_no_url_block');
$this->setBlock('files', 'modify_file', 'modify_file_block');
/*
$c = array('filter'	=> array('document','audio','video','image','other'),
		   'filterOptions' => array('document','audio','video','image','other'),
		   'sort'	=> 'Date',
		   'sortdir'	=> 'Desc',
		   'size' => 0,
			);
print serialize($c);
*/

require_once SITE_root.'/library/file.php';
$fileHandler = new File;

$fileList = $fileHandler->getAllInCategory($categoryID, $config);

if (is_array($fileList)) {
	foreach ($fileList as $file) {

		$output = array('FILE_DESCRIPTION'	=> scrubTEXT($file['description']),
						'FILE_ID'			=> $file['id'],
						'FILE_MIME_IMAGE'	=> $allowedFiles->getClass($file["iconFile"]),
						'FILE_POSTED_BY'	=> formatName($file),
						'FILE_POSTED_BY_ID'	=> $file['owner'],
						'FILE_POSTED_DATE'	=> outputDate(Cfg::get('date_short'), $file['submitted']),
						'FILE_COMMENT'		=> nl2br(scrubTEXT($file['comment'])),
						'ON_OFF'			=> ++$j%2? '' : 'dark',
                        'HIDDEN'            => $file['hidden'] ? 'hidden' : '',
						);
		$this->setVar($output);

		//set name
		if($file['realname'] != "") {

			if($file['inline'] == 'true' && FILE_use_frame) {
				$this->setVar('FILE_URL', '/sendframe.php?id='.$file['id']);
				$this->setVar('FILE_TARGET', '_blank');
			}
			else {
				$this->setVar('FILE_URL', '/send.php?id='.$file['id']);
				$this->setVar('FILE_TARGET', '_blank');
			}
			$this->setVar('file_no_url_block', '');
			$this->parse('file_url_block', 'file_url');
		}
		else {
			$this->setVar('file_url_block', '');
			$this->parse('file_no_url_block', 'file_no_url');

		}


		if($permission >= WRITE_ACCESS)
			$this->parse('modify_file_block', 'modify_file');
		else
			$this->setVar('modify_file_block','');

		$this->parse('files_block', 'files', true);

	}
} else
	$this->setVar('files_block', '');


/* Set up component name
 */
$this->setvar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Files'
));

/* filter config */
$config['filter'] = is_array($config['filter']) ? $config['filter'] : array($config['filter']);
$this->setBlock("config", "filter", "filter_block".$component['homepageComponentLocationID']);
foreach ($default['filterOptions'] as $option) {
    $output = array('COMPONENT_CONFIG_FILTER_VALUE' 	=> $option,
                    'COMPONENT_CONFIG_FILTER_NAME' 		=> ucwords($option),
                    'COMPONENT_CONFIG_FILTER_SELECTED' 	=> in_array($option, $config['filter']) ? 'checked="checked"' : '',
                    );
    $this->setVar($output);
    $this->parse("filter_block".$component['homepageComponentLocationID'], "filter", true);
}

//setup block for submission button
$this->setBlock($componentHandle, "submit_file", "submit_file_block");
if ($permission < WRITE_ACCESS)
	$this->setVar("submit_file_block", "");
else
	$this->parse("submit_file_block", "submit_file");

?>