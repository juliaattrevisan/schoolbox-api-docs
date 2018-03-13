-- Set all namespace slug suffixes

-- Default is #
UPDATE component_definition
SET msg_namespace_slug_suffix = '#'
WHERE msg_namespace_slug IS NOT NULL;

-- File components
UPDATE component_definition
SET msg_namespace_slug_suffix = 'file.#'
WHERE class IN (
    'Component_Homepage_AudioController',
    'Component_Homepage_FileListController',
    'Component_Homepage_ImageController',
    'Component_Homepage_ImageGalleryController',
    'Component_Homepage_ScrollerController',
    'Component_Homepage_VideoController'
);

-- Folder components
UPDATE component_definition
SET msg_namespace_slug_suffix = 'folder.#'
WHERE class IN (
    'Component_Homepage_FolderListController'
);

-- Text box components
UPDATE component_definition
SET msg_namespace_slug_suffix = 'textbox.#'
WHERE class IN (
    'Component_Homepage_TextBoxController'
);

-- Link components
UPDATE component_definition
SET msg_namespace_slug_suffix = 'link.#'
WHERE class IN (
    'Component_Homepage_LinkListController'
);
