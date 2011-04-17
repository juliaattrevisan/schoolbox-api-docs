<?php
require_once SITE_root . '/library/allowed-file.php';
$this->setTitle('Admin - Allowed Files');

$this->setFile(TEMPLATE_content, 'adminv2/allowed-files.html');
$this->setBlock(TEMPLATE_content, 'file', 'file_block');
$this->setBlock(TEMPLATE_content, 'image', 'image_block');

$allowedFile = new AllowedFile;

$fileList = $allowedFile->getAll('mime');

if (is_array($fileList)) {
    foreach($fileList as $file) {
        $output = array(
			'FILE_ID'				=> $file['fileTypeID'],
			'FILE_ENABLED'			=> $file['enable'] == 'true' ? 'Yes' : 'No',
			'FILE_ENABLED_CHECKED'  => $file['enable'] == 'true' ? 'checked="checked"' : '',
			'FILE_DESC'				=> scrubTEXT($file['description']),
			'FILE_MIME'				=> $file['mime'],
			'FILE_EXTENSION'		=> $file['extension'],
			'FILE_ICON'				=> $file['iconFile'],
			'FILE_INLINE'			=> $file['inline'] == 'true' ? 'Inline' : 'External',
		);

        $this->setVar($output);
        $this->parse('file_block','file',true);
    }
}

/* Output list of icons */
$iconList = $allowedFile->getIconList();

if (is_array($iconList)) {
    foreach($iconList as $icon) {
        $this->setVar('IMAGE_FILE', $icon);
        $this->parse('image_block','image',true);
    }
}
?>
