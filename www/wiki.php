<?php

use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Breadcrumbs;

$wikiHandler = new Wiki();
$groupHandler = new Group(HOMEPAGE_SERVICE);

/* Load a past revision
 */
if ($this->arg[0] == 'revision') {
    $wiki = $wikiHandler->getByID($this->arg[1], $this->arg[2]);

    $this->setFile(TEMPLATE_content, 'wiki.html');
    $this->setBlock(TEMPLATE_content, 'modify_link', 'modify_link_block');
    $this->setBlock(TEMPLATE_content, 'delete_link', 'delete_link_block');
    $this->setBlock(TEMPLATE_content, 'revert_link', 'revert_link_block');
    $this->setBlock(TEMPLATE_content, 'lock_link', 'lock_link_block');
    $this->setBlock(TEMPLATE_content, 'unlock_link', 'unlock_link_block');
    $this->setBlock(TEMPLATE_content, 'export_link', 'export_link_block');
    $this->setBlock(TEMPLATE_content, 'revision_list', 'revision_list_block');
    $this->setBlock(TEMPLATE_content, 'wiki_locked', 'wiki_locked_block');
    $this->setBlock('revision_list', 'revision_comparison', 'revision_comparison_block');

    /* Load just by id (from homepage)
      */
    if (!isset($wiki['wikiID'])) {
        error(101);
    }

    try{
        $folder = FolderMapper::getInstance()->findById($wiki['categoryID']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    // check permission
    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS) {
        error(19);
    }

    $this->setTitle('Wiki');

    $userHandler = new User();
    $modifiedDate = new Alaress_Date_Format($wiki['modified'], 'U');

    /* Detect stub links in the wiki body and determine whether the links should
      * be cosmetically customised
      *
      * Output wiki contents
      */
    $output = array(
        'WIKI_ID' => $wiki['wikiID'],
        'CATEGORY_ID' => $wiki['categoryID'],
        'FOLDER_NAME' => Scrub::text($folder->getName()),
        'HEADING' => Scrub::text($wiki['heading']),
        'BODY' => Scrub::html($wikiHandler->activateStubLinks($wiki['body'])),
        'MODIFIED' => $modifiedDate->getLong(),
        'AUTHOR' => $userHandler->formatName($wiki),
        'AUTHOR_ID' => $wiki['authorID'],
        'CURRENT_REVISION' => $wiki['revisionID'],
        'EXPORT' => 'Export',
    );

    $this->setVar($output);

    $locked = ($wiki['locked'] == 'true');
    if ($locked) {
        $this->parse('wiki_locked_block', 'wiki_locked');
    }
    else {
        $this->setVar(array(
            'wiki_locked_block' => '',
        ));
    }

    // output revisions
    $revisionList = $wikiHandler->getRevisionList($wiki['wikiID']);

    if (is_array($revisionList)) {
        $userHandler = new User();
        $i = 0;

        foreach ($revisionList as $revision) {
            $i++;

            $revisionDate = new Alaress_Date_Format($revision['created'], 'U');

            $this->setVar(array(
                'WIKI_ID' => $wiki['wikiID'],
                'REVISION_ID' => $wiki['revisionID'],
                'REVISION_COMPARE' => $revision['revisionID'],
                'REVISION_DATE' => $revisionDate->getDateOnly(),
                'REVISION_HEADING' => Scrub::text($revision['heading']),
                'REVISION_AUTHOR' => $userHandler->formatName($revision),
                'REVISION_CURRENT' => $revision['revisionID'] == $wiki['revisionID'] ? 'revisionCurrent' : '',
            ));

            if ($wiki['revisionID'] == $revision['revisionID']) {
                $this->setVar('revision_comparison_block');
            } else {
                $this->parse('revision_comparison_block', 'revision_comparison');
            }

            $this->parse('revision_list_block', 'revision_list', true);

            if ($i == 1) {
                $finalRevision = $revision['revisionID'];
            }
        }
    } else {
        $this->setVar('revision_list_block', '');
    }

    // output links
    $this->setVar('revert_link_block', '');
    if ($wiki['revisionID'] != $finalRevision && $permission >= WRITE_ACCESS) {
        $this->parse('revert_link_block', 'revert_link');
    }

    $this->setVar('delete_link_block', '');
    if ($permission >= WRITE_ACCESS) {
        $this->parse('delete_link_block', 'delete_link');
    }

    $this->setVar('lock_link_block', '');
    $this->setVar('unlock_link_block', '');
    if ($permission >= WRITE_ACCESS) {
        if ($locked) {
            $this->parse('unlock_link_block', 'unlock_link');
        } else {
            $this->parse('lock_link_block', 'lock_link');
        }
    }

    $this->setVar('modify_link_block', '');
    if (($permission >= READ_ACCESS && !$locked) || $permission >= WRITE_ACCESS) {
        $this->parse('modify_link_block', 'modify_link');
    }

    $this->setVar('export_link_block', '');
    if ($permission >= READ_ACCESS) {
        $this->parse('export_link_block', 'export_link');
    }

    $slug  = Scrub::text($wiki['slug']);
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Wiki',
            'href' => "/wiki/{$folder->getId()}/$slug",
            'class' => '',
        ],
        [
            'text' => 'View Revisions',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
/* Load an existing wiki by its category id and slug
 */
elseif (is_numeric($this->arg[0]) && strlen($this->arg[1])) {
    $wiki = $wikiHandler->getByCategoryAndSlug($this->arg[0], $this->arg[1]);

    /* If the wiki wasn't found, offer some information and a link
      * to a place where they can create it
      */
    if (!$wiki) {
        $this->setFile(TEMPLATE_content, 'wiki/new-wiki.html');

        $this->setVar(array(
            'WIKI_CATEGORYID' => $this->arg[0],
            'WIKI_SLUG' => $this->arg[1],
            'WIKI_COMPONENT_ID' => 0,
        ));

        try {
            $folderMapper = new FolderMapper();
            $folder = $folderMapper->findById($this->arg[0]);
        }
        catch (\Schoolbox\Mapper\Exception $e) {
            error(2);
        }

        /* Render out breadcrumbs
         */
        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => $folder->getName(),
                'href' => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'New Wiki',
                'href' => '',
                'class' => 'active',
            ]
        ]);
    }
    /* Otherwise load it
	 */
    else {
        $this->setFile(TEMPLATE_content, 'wiki.html');
        $this->setBlock(TEMPLATE_content, 'modify_link', 'modify_link_block');
        $this->setBlock(TEMPLATE_content, 'delete_link', 'delete_link_block');
        $this->setBlock(TEMPLATE_content, 'revert_link', 'revert_link_block');
        $this->setBlock(TEMPLATE_content, 'lock_link', 'lock_link_block');
        $this->setBlock(TEMPLATE_content, 'unlock_link', 'unlock_link_block');
        $this->setBlock(TEMPLATE_content, 'export_link', 'export_link_block');
        $this->setBlock(TEMPLATE_content, 'revision_list', 'revision_list_block');
        $this->setBlock(TEMPLATE_content, 'wiki_locked', 'wiki_locked_block');
        $this->setBlock('revision_list', 'revision_comparison', 'revision_comparison_block');

        /* Load just by id (from homepage)
           */
        if (!isset($wiki['wikiID'])) {
            error(101);
        }

        try{
            $folder = FolderMapper::getInstance()->findById($wiki['categoryID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            error(2);
        }

        // check permission
        if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS) {
            error(19);
        }

        $this->setTitle('Wiki');

        $userHandler = new User();
        $modifiedDate = new Alaress_Date_Format($wiki['modified'], 'U');

        /* Detect stub links in the wiki body and determine whether the links should
           * be cosmetically customised
           *
           * Output wiki contents
           */
        $output = array(
            'WIKI_ID' => $wiki['wikiID'],
            'CATEGORY_ID' => $wiki['categoryID'],
            'FOLDER_NAME' => Scrub::text($folder->getName()),
            'HEADING' => Scrub::text($wiki['heading']),
            'BODY' => Scrub::html($wikiHandler->activateStubLinks($wiki['body'])),
            'MODIFIED' => $modifiedDate->getLong(),
            'AUTHOR' => $userHandler->formatName($wiki),
            'AUTHOR_ID' => $wiki['authorID'],
            'CURRENT_REVISION' => $wiki['revisionID'],
            'EXPORT' => 'Export',
        );

        $this->setVar($output);

        $locked = ($wiki['locked'] == 'true');
        if ($locked) {
            $this->parse('wiki_locked_block', 'wiki_locked');
        }
        else {
            $this->setVar(array(
                'wiki_locked_block' => '',
            ));
        }

        // output revisions
        $revisionList = $wikiHandler->getRevisionList($wiki['wikiID']);

        if (is_array($revisionList)) {
            $userHandler = new User();
            $i = 0;

            foreach ($revisionList as $revision) {
                $i++;

                $revisionDate = new Alaress_Date_Format($revision['created'], 'U');

                $this->setVar(array(
                    'WIKI_ID' => $wiki['wikiID'],
                    'REVISION_ID' => $wiki['revisionID'],
                    'REVISION_COMPARE' => $revision['revisionID'],
                    'REVISION_DATE' => $revisionDate->getDateOnly(),
                    'REVISION_HEADING' => Scrub::text($revision['heading']),
                    'REVISION_AUTHOR' => $userHandler->formatName($revision),
                    'REVISION_CURRENT' => $revision['revisionID'] == $wiki['revisionID'] ? 'revisionCurrent' : '',
                ));

                if ($wiki['revisionID'] == $revision['revisionID']) {
                    $this->setVar('revision_comparison_block');
                } else {
                    $this->parse('revision_comparison_block', 'revision_comparison');
                }

                $this->parse('revision_list_block', 'revision_list', true);

                if ($i == 1) {
                    $finalRevision = $revision['revisionID'];
                }
            }
        } else {
            $this->setVar('revision_list_block', '');
        }

        // output links
        $this->setVar('revert_link_block', '');
        if ($wiki['revisionID'] != $finalRevision && $permission >= WRITE_ACCESS) {
            $this->parse('revert_link_block', 'revert_link');
        }

        $this->setVar('delete_link_block', '');
        if ($permission >= WRITE_ACCESS) {
            $this->parse('delete_link_block', 'delete_link');
        }

        $this->setVar('lock_link_block', '');
        $this->setVar('unlock_link_block', '');
        if ($permission >= WRITE_ACCESS) {
            if ($locked) {
                $this->parse('unlock_link_block', 'unlock_link');
            } else {
                $this->parse('lock_link_block', 'lock_link');
            }
        }

        $this->setVar('modify_link_block', '');
        if (($permission >= READ_ACCESS && !$locked) || $permission >= WRITE_ACCESS) {
            $this->parse('modify_link_block', 'modify_link');
        }

        $this->setVar('export_link_block', '');
        if ($permission >= READ_ACCESS) {
            $this->parse('export_link_block', 'export_link');
        }

        $slug = Scrub::text($wiki['slug']);

        /* Render out breadcrumbs
         */
        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => $folder->getName(),
                'href' => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Wiki',
                'href' => "/wiki/{$folder->getId()}/$slug",
                'class' => '',
            ],
            [
                'text' => 'View',
                'href' => '',
                'class' => 'active',
            ]
        ]);
    }
}
else {
    error(2);
}
