<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;
use Scrub;

/* Folder specific
 */
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder;

class Renderer extends Base
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'folder';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Folders';

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param $type
     */
    public function render(MapperResult $mapperResult, $type)
    {
        if ($mapperResult->getCount()) {
            $this->getPage()->setFile('folder_t', 'resource/folder/search/results.html');
            $this->getPage()->setBlock('folder_t', 'folder', 'folder_block');
            $this->getPage()->setBlock('folder_t', 'see_more', 'see_more_block');
            $this->getPage()->setBlock('folder', 'modify', 'modify_block');

            $this->getPage()->setVar(array(
                'see_more_block' => '',
            ));

            $this->getPage()->setVar(array(
                'FOLDER_DISP_COUNT' => $mapperResult->getResult()->count(),
                'FOLDER_COUNT' => $mapperResult->getCount(),
            ));

            /* phplib
             */
            foreach ($mapperResult->getResult() as $currentFolder) {
                /** @var Folder\Model $currentFolder */

                /* Manufacture a formatted name with codes, if any
                 */
                if ($currentFolder->getCodes()->count()) {
                    $allCodes = array();

                    foreach ($currentFolder->getCodes() as $currentCode) {
                        $allCodes[] = $currentCode->getCode();
                    }

                    $suffix = implode(', ', $allCodes);
                    $folderName = "{$currentFolder->getName()} ({$suffix})";
                } else {
                    $folderName = "{$currentFolder->getName()}";
                }

                /* Is this a homepage or just a folder
                 */
                if (is_numeric($currentFolder->getHomepage()->getTemplate()->getId())) {
                    $folderType = 'home';
                } else {
                    $folderType = 'folder';
                }

                /* phplib
                 */
                $this->getPage()->setVar(array(
                    'FOLDER_ID' => $currentFolder->getId(),
                    'FOLDER_NAME' => Scrub::text($folderName),
                    'FOLDER_BREADCRUMB' => $this->spawnBreadcrumbs($currentFolder),
                    'FOLDER_TYPE' => $folderType,
                ));

                $userAccessMapper = new UserAccessMapper();
                $userAccess = $userAccessMapper->findByColumnList(array(
                    'uid' => $_SESSION['user']->getId(),
                    'service' => HOMEPAGE_SERVICE,
                    'sid' => $currentFolder->getId()
                ));

                $roleAccessMapper = new RoleAccessMapper();
                $roleAccess = $roleAccessMapper->findByColumnList(array(
                    'cid' => $currentFolder->getId(),
                    'gid' => $_SESSION['user']->getRole()->getId(),
                ));

                /* Only show modify block for those with access >= 2
                 */
                if ($userAccess->count() && $roleAccess->count()) {
                    $hasRoleAccess = ($roleAccess->first()->getType() >= 2);
                    $hasUserAccess = ($userAccess->first()->getAccessLevel() >= 2);

                    if ($hasRoleAccess && $hasUserAccess) {
                        $this->getPage()->parse('modify_block', 'modify');
                    } else {
                        $this->getPage()->parse('modify_block', 'modify');
                    }
                } else {
                    $this->getPage()->parse('modify_block', 'modify');
                }

                $this->getPage()->parse('folder_block', 'folder', true);
            }

            /* "outputMore"
             */
            if ($type == 'all' && $mapperResult->getCount() > \Cfg::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            }

            $this->getPage()->parse('RESULT', 'folder_t', true);
        } else {
            return;
        }
    }
}
