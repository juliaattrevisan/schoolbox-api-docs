<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;


use Schoolbox\Collection;
use SitePage;

class Breadcrumbs extends \Schoolbox\Breadcrumbs
{

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
        /** @var Collection|Model[] $list */
        $list = $this->mapper->breadcrumb($folder, $includeFolder);

        // let's just calculate this once
        $last = $list->count() - 1;

        $data = [];

        foreach ($list as $i => $parent) {
            $data[] = [
                'href'  => '/homepage/'.$parent->getId(),
                'text'  => $parent->getName(),
                'class' => $i == $last ? 'active' : ''
            ];
        }

        parent::renderIntoSitePage($page, $handle, $data);
    }

}