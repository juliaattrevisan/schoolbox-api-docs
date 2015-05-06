<?php

use Schoolbox\Collection;
use Schoolbox\File\ExtensionMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

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

/** @var Collection|Mimetype[] $mimes */
$mimes = MimetypeMapper::getInstance()->findAll([ 'deleted' => false, 'order' => [ 'mime' => 'asc' ] ]);

$default_size = Alaress_Byte_Format::bytesToString(Config::get('max_file_size'));
$placeholder  = "default ($default_size)";

if ($mimes->count()) {
    foreach ($mimes as $mime) {

        $extensions   = ExtensionMapper::getInstance()->findByColumn('fileTypeID', $mime->getId());
        $extensions   = $extensions->getColumnValues('extension');
        $extensionCSV = implode(',', $extensions);
        if (count($extensions)) {
            $extensions = '<li>' . implode('</li><li>', $extensions) . '</li>';
        } else {
            $extensions = '(none)';
        }

        $max_size_output = Alaress_Byte_Format::bytesToString($mime->getMaxFileSize());
        $output = array(
            'FILE_ID'                  => $mime->getId(),
            'FILE_ENABLED'             => $mime->isEnabled() ? 'Yes' : 'No',
            'FILE_ENABLED_CHECKED'     => $mime->isEnabled() ? 'checked="checked"' : '',
            'FILE_DESC'                => Scrub::text($mime->getDescription()),
            'FILE_MIME'                => $mime->getMime(),
            'FILE_EXTENSION'           => $extensions,
            'FILE_EXTENSION_CSV'       => $extensionCSV,
            'FILE_ICON'                => $mime->getIconFile(),
            'FILE_MAXSIZE'             => $mime->getMaxFileSize() ? $max_size_output : "<em>$placeholder</em>",
            'FILE_MAXSIZE_INPUT'       => $mime->getMaxFileSize() ? $max_size_output : '',
            'FILE_MAXSIZE_PLACEHOLDER' => htmlspecialchars($placeholder, ENT_QUOTES),
            'FILE_INLINE'              => $mime->getInline() ? 'Inline' : 'External',
            'FILE_EXTERN_CHECKED'      => $mime->getInline() ? '' : 'selected="selected"',
        );
        $this->setVar($output);
        $this->parse('file_block','file',true);
    }
}

/* Output list of icons */
$allowedFile = new AllowedFile();
$iconList = $allowedFile->getIconList();

if (is_array($iconList)) {
    foreach($iconList as $icon) {
        $this->setVar('IMAGE_FILE', $icon);
        $this->parse('image_block','image',true);
    }
}
