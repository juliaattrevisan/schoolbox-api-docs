<?php

use \Schoolbox\Breadcrumbs;

$folderMapper = new \Schoolbox\Resource\CategoryMapper();
$groupHandler = new Group();

if (is_numeric($this->arg[0])) {
    try {
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
        error(2);
    }

    if ($folder->getParent()->getId() == 0) {
        $folderParent = new \Schoolbox\Resource\Category();
        $folderParent->setId(0);
        $folderParent->setName('Top');
    } else {
        try {
            $folderParent = $folderMapper->findById($folder->getParent()->getId());
        } catch (\Exception $e) {
            error(2);
        }
    }

    if ($groupHandler->getPermission($folder->getId()) < WRITE_ACCESS) {
        error(6);
    }

    $this->setFile(TEMPLATE_content, "cms/modify-category.html");
    $this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
    $this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
    $this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');
    $this->setBlock(TEMPLATE_content, 'reset_content', 'reset_content_block');
    $this->setBlock(TEMPLATE_content, 'synergy_input', 'synergy_input_block');
    $this->setBlock(TEMPLATE_content, 'recursive', 'recursive_block');
    $this->setBlock(TEMPLATE_content, 'curriculum_code_input', 'curriculum_code_input_block');

    if ($groupHandler->isSuperuser()) {
        $this->parse('recursive_block', 'recursive');
    } else {
        $this->setVar('recursive_block', '');
    }

    if (Config::get('curriculum_tagging_enabled')) {
        $this->parse('curriculum_code_input_block', 'curriculum_code_input');
    } else {
        $this->setVar('curriculum_code_input_block', '');
    }

    $this->setTitle('Modify Category');
    $this->setTab('resources');

    $output = array(
        'ACTION' => 'Modify',
        'CATEGORY_ID' => $folder->getId(),
        'NAME' => Scrub::text($folder->getName()),
        'CODE' => Scrub::text($folder->getCodeString()),
        'PARENT_ID' => $folder->getParent()->getId(),
        'PARENT_NAME' => Scrub::text($folderParent->getName()),
        'USER_GROUP' => $_SESSION['gid'],
        'DATABASE_TYPE' => ucfirst(Cfg::get('external_type')),
        'REQUIRED_PERMS' => $_SESSION['gid'],
        'CURRICULUM_CODE_INPUT' => $this->getTwig()->render(
            'sub/curriculumCodeSelector.twig',
            ['codeList' => $folder->getCurriculumNodes()]
        ),
    );

    $this->setVar($output);

    $homepageTemplateMapper = new \Schoolbox\Resource\HomepageTemplateMapper();
    $homepageTemplateList = $homepageTemplateMapper->findAll();

    /* Used to remove homepages
     */
    $noHomepage = new \Schoolbox\Resource\HomepageTemplate;
    $noHomepage->setName('No Homepage')->setId(-1);
    $homepageTemplateList->prepend($noHomepage);

    /* Keep existing homepage
     */
    $keepExisting = new \Schoolbox\Resource\HomepageTemplate;
    $keepExisting->setName('Keep Existing')->setId(0);
    $homepageTemplateList->prepend($keepExisting);

    $this->dropDown('template_list', $homepageTemplateList, '', TEMPLATE_content, array('name'=>'TEMPLATE_NAME','id'=>'TEMPLATE_ID' ));

    $roleMapper = new \Schoolbox\Permission\Access\RoleAccessMapper();
    $allRoleAccess = $roleMapper->getGroupPermissions($folder);

    if ($allRoleAccess->count()) {
        foreach ($allRoleAccess as $currentRoleAccess) {
            $this->setVar(array(
                'GROUP_NAME' => Scrub::text($currentRoleAccess->getRole()->getName()),
                'GROUP_ID' => $currentRoleAccess->getRole()->getId(),
                'PERM_0_SELECTED' => '',
                'PERM_1_SELECTED' => '',
                'PERM_2_SELECTED' => '',
                'PERM_' . $currentRoleAccess->getType() . '_SELECTED' => 'checked="checked"',
            ));

            $this->parse('perm_table_block', 'perm_table', true);
        }
    } else {
        $this->setVar('perm_table_block', '');
    }

    $userAccessMapper = new \Schoolbox\Permission\Access\UserAccessMapper();

    if ($_SESSION['user']->isSuperuser() || $userAccessMapper->isFolderOwner($_SESSION['user'], $folder)) {
        $this->setVar('indiv_perms_block', '');
        $this->parse('indiv_modify_block', 'indiv_modify');
    } else {
        if ($userAccessMapper->hasFolderOwner($folder)) {
            $this->setVar('indiv_perms_block', '');
            $this->setVar('indiv_modify_block', '');
        } else {
            $this->parse('indiv_perms_block', 'indiv_perms');
            $this->setVar('indiv_modify_block', '');
        }
    }

    $this->parse('reset_content_block', 'reset_content');

    if ($groupHandler->isSuperuser()) {
        $this->parse('synergy_input_block', 'synergy_input');
    } else {
        $this->setVar('synergy_input_block', '');
    }

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folderParent->getName(),
            'href' => "/homepage/{$folderParent->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Modify Folder',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
else {
    if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
        if ($this->arg[1] == 0) {
            $folderParent = new \Schoolbox\Resource\Category();
            $folderParent->setId(0);
            $folderParent->setName('Top');
        } else {
            try {
                $folderParent = $folderMapper->findById($this->arg[1]);
            } catch (\Exception $e) {
                error(2);
            }
        }

        if ($groupHandler->getPermission($folderParent->getId()) < WRITE_ACCESS) {
            error(6);
        }

        $this->setFile(TEMPLATE_content, 'cms/modify-category.html');
        $this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
        $this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
        $this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');
        $this->setBlock(TEMPLATE_content, 'reset_content', 'reset_content_block');
        $this->setBlock(TEMPLATE_content, 'synergy_input', 'synergy_input_block');
        $this->setBlock(TEMPLATE_content, 'recursive', 'recursive_block');
        $this->setBlock(TEMPLATE_content, 'curriculum_code_input', 'curriculum_code_input_block');

        // don't add recursion option for a new folder, it's empty, makes no sense
        $this->setVar('recursive_block', '');

        // -_-
        if (Config::get('curriculum_tagging_enabled')) {
            $this->parse('curriculum_code_input_block', 'curriculum_code_input');
        } else {
            $this->setVar('curriculum_code_input_block', '');
        }

        $this->setTitle('Add Category');
        $this->setTab('resources');

        $this->setVar(array(
            'ACTION' => 'Add',
            'CATEGORY_ID' => '',
            'NAME' => '',
            'CODE' => '',
            'CURRICULUM_CODE_LIST' => json_encode([]),
            'PARENT_ID' => $folderParent->getId(),
            'PARENT_NAME' => Scrub::text($folderParent->getName()),
            'REQUIRED_PERMS' => $_SESSION['gid'],
            'GROUP_NAME' => '',
            'DATABASE_TYPE' => ucfirst(Cfg::get('database_type')),
            'CURRICULUM_CODE_INPUT' => $this->getTwig()->render(
                'sub/curriculumCodeSelector.twig',
                ['codeList' => []]
            ),
        ));

        $homepageTemplateMapper = new \Schoolbox\Resource\HomepageTemplateMapper();
        $homepageTemplateList = $homepageTemplateMapper->findAll();

        /* Used to remove homepages
         */
        $noHomepage = new \Schoolbox\Resource\HomepageTemplate;
        $noHomepage->setName('No Homepage')->setId(-1);
        $homepageTemplateList->prepend($noHomepage);

        $this->dropDown('template_list', $homepageTemplateList, '', TEMPLATE_content, array('name'=>'TEMPLATE_NAME','id'=>'TEMPLATE_ID' ));

        /* Suggest Group Permissions based on parent folder's Group Permissions
		 */
        $roleAccessMapper = new \Schoolbox\Permission\Access\RoleAccessMapper();

        $allParentPermissions = $roleAccessMapper->getGroupPermissions($folderParent);

        if ($allParentPermissions->count()) {
            foreach ($allParentPermissions as $parentPermission) {
                $this->setVar(array(
                    'GROUP_ID' => $parentPermission->getRole()->getId(),
                    'GROUP_NAME' => Scrub::text($parentPermission->getRole()->getName()),
                    'PERM_0_SELECTED' => '',
                    'PERM_1_SELECTED' => '',
                    'PERM_2_SELECTED' => '',
                ));

                /* Preselect permission radio
				 */
                if (is_numeric($parentPermission->getType()) && in_array($parentPermission->getType(), array(0,1,2))) {
                    $key = "PERM_{$parentPermission->getType()}_SELECTED";

                    $this->setVar(array(
                        $key => 'checked="checked"',
                    ));
                } /* Set "None" by default if unspecified
				 */
                else {
                    $this->setVar(array(
                        'PERM_0_SELECTED' => 'checked="checked"',
                    ));
                }

                $this->parse('perm_table_block', 'perm_table', true);
            }
        } else {
            // Show all roles
            $roleMapper = new \Schoolbox\RoleMapper();
            foreach ($roleMapper->findAll(['order' => ['id' => 'asc']]) as $role) {
                $this->setVar([
                    'GROUP_ID'        => $role->getId(),
                    'GROUP_NAME'      => Scrub::text($role->getName()),
                    'PERM_0_SELECTED' => '',
                    'PERM_1_SELECTED' => '',
                    'PERM_2_SELECTED' => '',
                ]);
                $this->parse('perm_table_block', 'perm_table', true);
            }
        }

        $this->parse('indiv_perms_block', 'indiv_perms');
        $this->setVar('indiv_modify_block', '');
        $this->setVar('reset_content_block', '');

        if ($groupHandler->isSuperuser()) {
            $this->parse('synergy_input_block', 'synergy_input');
        } else {
            $this->setVar('synergy_input_block', '');
        }

        /* Render out breadcrumbs
         */
        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => $folderParent->getName(),
                'href' => "/homepage/{$folderParent->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Add Folder',
                'href' => '',
                'class' => 'active',
            ]
        ]);
    } else {
        if ($this->arg[0] == 'reset' && is_numeric($this->arg[1])) {
            try {
                $folder = $folderMapper->findById($this->arg[1]);
            } catch (\Exception $e) {
                error(13);
            }

            if ($groupHandler->getPermission($folder->getId()) < WRITE_ACCESS) {
                error(6);
            }

            // remove component content
            $homepage = new Homepage($folder->getId());
            $homepage->emptyHomepage();

            /**
            * @see SB-2793
            * Don't recursively delete the category contents
            */
//			$categoryHandler->recursiveDelete($category['id'], false);

            /* Delete all files */
            $fileMapper = Schoolbox\Resource\File\Mapper::getInstance();
            $fileList = $fileMapper->findForFolders([ $folder->getId() ]);
            foreach ($fileList as $file) {
                $fileMapper->delete($file);
            }

            /* Delete all links
			 */
            $linkMapper = new \Schoolbox\Resource\LinkMapper();
            $allLinks = $linkMapper->findAllInCategory($folder, array());

            foreach ($allLinks as $currentLink) {
                $linkMapper->delete($currentLink);
            }

            SitePage::localRedir("/homepage/{$folder->getId()}");
        }
    }
}
