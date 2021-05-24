<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use SitePage;

class Breadcrumbs
{
    const TPL = 'breadcrumbTpl';


    /**
     * @param SitePage $page
     * @param string   $handle
     * @param array    $data
     */
    public function renderIntoSitePage(SitePage $page, $handle, array $data)
    {
        $page->setFile(self::TPL, 'resources/breadcrumbs.html');

        $page->setBlock(self::TPL, 'breadcrumb', 'breadcrumb_block');
        $page->setVar('breadcrumb_block', '');

        foreach ($data as $crumb) {
            $page->setVar('BREADCRUMB_HREF', $crumb['href']);
            $page->setVar('BREADCRUMB_TEXT', \Scrub::text($crumb['text']));
            $page->setVar('BREADCRUMB_CLASS', $crumb['class']);

            $page->parse('breadcrumb_block', 'breadcrumb', true);
        }

        $page->parse($handle, self::TPL);
    }


    /**
     * I don't have a better place for this to live, sorry -_-;
     *
     * @param \Schoolbox\Resource\Folder\Model $folder
     * @param string $returnUrl
     * @return array
     */
    public function getFolderCrumbs($folder, $returnUrl = '')
    {
        if (!$returnUrl || strpos(urldecode(base64_decode($returnUrl)), '/course/modify/') === false) {
            // not a course modify path, or no return URL: just give us the homepage version
            return [
                [
                    'text'  => $folder->getName(),
                    'href'  => "/homepage/{$folder->getId()}",
                    'class' => '',
                ]
            ];
        }

        // course modify path: obtain parent and use that
        $parent = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($folder->getParent()->getId());

        return [
            [
                'text'  => 'Courses',
                'href'  => '/course/list',
                'class' => '',
            ],
            [
                'text'  => $parent->getName(),
                'href'  => "/course/modify/{$parent->getCourseSpecification()->getId()}",
                'class' => '',
            ],
            [
                'text'  => $folder->getName(),
                'href'  => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
        ];
    }
}
