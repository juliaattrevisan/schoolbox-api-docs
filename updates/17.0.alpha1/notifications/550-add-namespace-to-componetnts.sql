ALTER TABLE `component_definition`
  ADD COLUMN `msg_namespace_slug` VARCHAR(32) NULL AFTER `multiple`,
  ADD FOREIGN KEY (`msg_namespace_slug`) REFERENCES `schoolbox`.`msg_namespace`(`slug`)
    ON UPDATE NO ACTION
    ON DELETE SET NULL;


UPDATE component_definition SET msg_namespace_slug = 'duework'
WHERE class = 'Schoolbox\\Learning\\Assessment\\DueWork\\Component\\Homepage\\Controller';

UPDATE component_definition SET msg_namespace_slug = 'assess'
WHERE class = 'Schoolbox\\Learning\\Assessment\\Quiz\\Component\\Homepage\\Controller';

UPDATE component_definition SET msg_namespace_slug = 'blog'
WHERE class = 'Schoolbox\\Blog\\Homepage\\Component\\Controller';

UPDATE component_definition SET msg_namespace_slug = 'forum'
WHERE class = 'Component_Homepage_ForumListController';

UPDATE component_definition SET msg_namespace_slug = 'news'
WHERE class = 'Schoolbox\\News\\Component\\Homepage\\Controller';

UPDATE component_definition SET msg_namespace_slug = 'calendar'
WHERE class = 'Component_Homepage_EventListController';

UPDATE component_definition SET msg_namespace_slug = 'wiki'
WHERE class = 'Component_Homepage_WikiController';

UPDATE component_definition SET msg_namespace_slug = 'group'
WHERE class = 'Component_Homepage_MembersController';

UPDATE component_definition SET msg_namespace_slug = 'cms'
WHERE class IN (
    'Component_Homepage_AudioController',
    'Component_Homepage_FileListController',
    'Component_Homepage_FolderListController',
    'Component_Homepage_ImageController',
    'Component_Homepage_ImageGalleryController',
    'Component_Homepage_LinkListController',
    'Component_Homepage_ScrollerController',
    'Component_Homepage_TextBoxController',
    'Component_Homepage_VideoController'
);

UPDATE component_definition SET msg_namespace_slug = 'sstream'
WHERE class = 'Component_Homepage_SocialStreamController';

UPDATE component_definition SET msg_namespace_slug = 'sports_fixtures'
WHERE class = 'Schoolbox\\Fixtures\\Component\\Homepage\\Controller';
