<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
    protected function sitePage(SitePage $page, $handle, array $data)
    {
        $page->setFile(self::TPL, 'resources/breadcrumbs.html');

        $page->setBlock(self::TPL, 'breadcrumb', 'breadcrumb_block');
        $page->setVar('breadcrumb_block', '');

        foreach ($data as $crumb) {
            $page->setVar('BREADCRUMB_HREF',  $crumb['href']);
            $page->setVar('BREADCRUMB_TEXT',  $crumb['text']);
            $page->setVar('BREADCRUMB_CLASS', $crumb['class']);

            $page->parse('breadcrumb_block', 'breadcrumb', true);
        }

        $page->parse($handle, self::TPL);

    }

}