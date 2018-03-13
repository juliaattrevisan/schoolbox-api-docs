-- Make every CMS namespace slug more specific
UPDATE component_definition
SET msg_namespace_slug = 'cms.file'
WHERE class IN (
    'Component_Homepage_AudioController',
    'Component_Homepage_FileListController',
    'Component_Homepage_ImageController',
    'Component_Homepage_ImageGalleryController',
    'Component_Homepage_ScrollerController',
    'Component_Homepage_VideoController'
);
UPDATE component_definition
SET msg_namespace_slug = 'cms.folder'
WHERE class IN (
    'Component_Homepage_FolderListController'
);
UPDATE component_definition
SET msg_namespace_slug = 'cms.textbox'
WHERE class IN (
    'Component_Homepage_TextBoxController'
);
UPDATE component_definition
SET msg_namespace_slug = 'cms.link'
WHERE class IN (
    'Component_Homepage_LinkListController'
);
