<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setTitle('Admin - Allowed Files');
$this->setFile(TEMPLATE_content, 'adminv2/allowed-files.html');
$this->setBlock(TEMPLATE_content, 'file', 'file_block');
$this->setBlock(TEMPLATE_content, 'image', 'image_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'allowed-file');

$allowedFile = new AllowedFile;

$fileList = $allowedFile->getAll('mime');

$default_size = Alaress_Byte_Format::bytesToString(Config::get('max_file_size'));
$placeholder  = "default ($default_size)";
if (is_array($fileList)) {
    foreach ($fileList as $file) {
        $max_size_output = Alaress_Byte_Format::bytesToString($file['max_file_size']);
        $output = array(
            'FILE_ID'                  => $file['fileTypeID'],
            'FILE_ENABLED'             => $file['enable'] == 'true' ? 'Yes' : 'No',
            'FILE_ENABLED_CHECKED'     => $file['enable'] == 'true' ? 'checked="checked"' : '',
            'FILE_DESC'                => Scrub::text($file['description']),
            'FILE_MIME'                => $file['mime'],
            'FILE_EXTENSION'           => $file['extension'],
            'FILE_ICON'                => $file['iconFile'],
            'FILE_MAXSIZE'             => $file['max_file_size'] ? $max_size_output : "<em>$placeholder</em>",
            'FILE_MAXSIZE_INPUT'       => $file['max_file_size'] ? $max_size_output : '',
            'FILE_MAXSIZE_PLACEHOLDER' => htmlspecialchars($placeholder, ENT_QUOTES),
            'FILE_INLINE'              => $file['inline'] == 'true' ? 'Inline' : 'External',
            'FILE_EXTERN_CHECKED'      => $file['inline'] == 'true' ? '' : 'selected="selected"',
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
