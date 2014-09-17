<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment;


use SitePage;
use SocialStream\Browser\Node;
use SocialStream\Browser\NodeRenderer;

abstract class NavigatorController extends AbstractController {

    /**
     * @param SitePage $page
     * @return void
     */
    protected function pparse(SitePage $page)
    {
        /* Get the stuff in this node */
        $browser = new Node(0);
        $nodeData = $browser->render();

        /* Render the stuff in this node */
        $renderer = new NodeRenderer($page, $nodeData);

        $renderer->render('BROWSER');
    }

}