<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;


use SitePage;

class Breadcrumbs
{

    const TPL = 'breadcrumbTpl';

    /** @var Mapper */
    protected $mapper;


    /**
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * @param SitePage $page
     * @param Model    $folder
     * @param bool     $includeFolder
     * @param string   $handle
     */
    public function renderIntoSitePage(SitePage $page, $handle, Model $folder, $includeFolder = false)
    {
        $page->setFile(self::TPL, 'resources/breadcrumbs.html');

        $page->setBlock(self::TPL, 'breadcrumb', 'breadcrumb_block');
        $page->setVar('breadcrumb_block', '');

        $list = $this->mapper->findParents($folder);

        // if the provided folder is to be included in the trail or not
        if ($includeFolder) {
            $list->add($folder);
        }

        // let's just calculate this once
        $last = $list->count() - 1;

        foreach ($list as $i => $parent) {
            $page->setVar('BREADCRUMB_FOLDER_ID', $parent->getId());
            $page->setVar('BREADCRUMB_FOLDER_NAME', $parent->getName());
            $page->setVar('BREADCRUMB_CLASS', $i == $last ? 'active' : '');
            $page->parse('breadcrumb_block', 'breadcrumb', true);
        }

        $page->parse($handle, self::TPL);
    }

}