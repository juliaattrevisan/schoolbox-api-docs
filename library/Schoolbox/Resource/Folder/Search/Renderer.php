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
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

class Renderer extends Base {

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
     * Render clickable tab menu
     *
     * @param MapperResult $mapperResult
     * @param $type
     */
    public function renderTab(MapperResult $mapperResult, $type) {
        if ($mapperResult->getCount()) {
            $this->getPage()->setVar(array(
                'TYPE_TYPE' => $this->getType(),
                'TYPE_NAME' => $this->getTabName(),
                'TYPE_COUNT' => $mapperResult->getCount(),
            ));

            /* Rage these styles in, because of css specificity
             */
            if ($this->getType() == 'all') {
                $this->getPage()->setVar(array(
                    'TYPE_CLASS' => ''
                ));
            }
            elseif (!$mapperResult->getCount()) {
                $this->getPage()->setVar(array(
                    'TYPE_CLASS' => 'hide'
                ));
            }
            else {
                $this->getPage()->setVar(array(
                    'TYPE_CLASS' => ''
                ));
            }

            $this->getPage()->parse('type_block', 'type', true);

            $this->getPage()->setVar(array(
                'RESULT_TYPE' => $this->getType(),
            ));

            $this->getPage()->parse('result_block', 'result', true);
        }
    }

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param $type
     */
    public function render(MapperResult $mapperResult, $type)
    {
        if ($mapperResult->getCount()) {
            $this->getPage()->setFile('category_t', 'resource/folder/search/results.html');
            $this->getPage()->setBlock('category_t', 'category', 'category_block');
            $this->getPage()->setBlock('category', 'modify', 'modify_block');

            $this->getPage()->setVar(array(
                'see_more_block' => ''
            ));

            $this->getPage()->setVar(array(
                'CATEGORY_DISP_COUNT' => $mapperResult->getResult()->count(),
                'CATEGORY_COUNT' => $mapperResult->getCount(),
            ));

            /* phplib
             */
            foreach ($mapperResult->getResult() as $currentFolder) {

                /* Manufacture a formatted name with codes, if any
                 */
                if ($currentFolder->getCodes()->count()) {
                    $allCodes = array();

                    foreach($currentFolder->getCodes() as $currentCode) {
                        $allCodes[] = $currentCode->getCode();
                    }

                    $suffix = implode(', ', $allCodes);
                    $folderName = "{$currentFolder->getName} ({$suffix})";
                }
                else {
                    $folderName = "{$currentFolder->getName()}";
                }

                /* Is this a homepage or just a folder
                 */
                if (is_numeric($currentFolder->getHomepage()->getTemplate()->getId())) {
                    $folderType = 'home';
                }
                else {
                    $folderType = 'folder';
                }

                /* phplib
                 */
                $this->getPage()->setVar(array(
                    'CATEGORY_ID' => $currentFolder->getId(),
                    'CATEGORY_NAME' => Scrub::text($folderName),
                    'CATEGORY_CODE' => $currentFolder->getCodes()->first()->getCode(),
                    'CATEGORY_BREADCRUMB' => $this->spawnBreadcrumbs($currentFolder),
                    'CATEGORY_COUNT' => $this->getCount($this->getType()),
                    'CATEGORY_TYPE' => $folderType,
                ));

                $this->getPage()->parse('category_block', 'category', true);
            }

            /* "outputMore"
             */
            if ($type == 'all' && $mapperResult->getCount() > \Cfg::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            }

            $this->getPage()->parse('RESULT', 'category_t', true);
        }
        else {
            return;
        }
    }

    /**
     * Breadcrumbs haha
     *
     * @param Category $currentFolder
     * @return string
     */
    private function spawnBreadcrumbs(Category $currentFolder) {
        $folderMapper = new CategoryMapper();
        $allCrumbs = $folderMapper->breadcrumb($currentFolder);

        $html = array();

        foreach($allCrumbs as $currentCrumb) {
            $html[] = "<a href=\"/homepage/{$currentCrumb->getId()}\">{$currentCrumb->getName()}</a>";
        }

        return implode(' > ', $html);
    }
}