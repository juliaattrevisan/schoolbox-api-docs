<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Search;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;
use Scrub;

/* Folder specific
 */
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
                if (is_object($currentFolder->getHomepage())) {
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

                if (Access::getInstance()->getFolderAccess($_SESSION['user'], $currentFolder->getId()) >= UserAccess::ACCESS_WRITE) {
                    $this->getPage()->parse('modify_block', 'modify');
                } else {
                    $this->getPage()->setVar('modify_block');
                }

                $this->getPage()->parse('folder_block', 'folder', true);
            }

            /* "outputMore"
             */
            if ($type == 'all' && $mapperResult->getCount() > \Config::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            }

            $this->getPage()->parse('RESULT', 'folder_t', true);
        } else {
            return;
        }
    }
}
