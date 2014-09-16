<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$this->setFile(TEMPLATE_content, 'resource_browser.html');

$treeBuilder = new Schoolbox\Resource\CategoryTreeBuilder(
    Schoolbox\Resource\CategoryMapper::getInstance(),
    Schoolbox\Assessment\Course\TaskMapper::getInstance(),
    new Group()
);

$rb = new Resource_Browser($this, $treeBuilder);

// Options:
// destination:     a destination selector, this element is updated
// destinationText: a text element to update with what we selected
// base:            a resource in the tree to use as the base, you can't go further up
// start:           a resource that is used as the default selected
// triggerChange:   true if the change event gets triggered on the destination
$options = array(
    'destination'      => $_REQUEST['destination'],
    'destination_text' => $_REQUEST['destinationText'],
    'base'             => $_REQUEST['base'],
    'start'            => $_REQUEST['start'],
    'trigger'          => $_REQUEST['triggerChange'],
);
$rb->render(TEMPLATE_content, $options);
$this->setAjax();
$this->template->pparse('finaloutput', TEMPLATE_content);
