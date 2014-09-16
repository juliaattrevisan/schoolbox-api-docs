<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Resource_Browser {

    /**
     * @var Page
     */
    private $page;

    /**
     * @var Schoolbox\Resource\CategoryTreeBuilder
     */
    protected $treeBuilder;

    public function __construct(Page $page, Schoolbox\Resource\CategoryTreeBuilder $treeBuilder) {
        $this->page        = $page;
        $this->treeBuilder = $treeBuilder;
    }

    public function render($templateHandle, $options) {
        $page = $this->getPage();
        $page->setFile($templateHandle, 'resource_browser.html');
        $page->setBlock($templateHandle, 'category_list', 'category_list_block');
        $page->setVar('DESTINATION',      $options['destination']);
        $page->setVar('DESTINATION_TEXT', $options['destination_text']);
        $page->setVar('TRIGGER',          $options['trigger'] ? '1' : '');

        $groupHandler = new Group();
        if (isset($options['start'])) {
            $openCategory = $options['start'];
        }
        else {
            $openCategory = (int)$_SESSION['resourcesID'];
        }

        // check permissions on selected category
        if ($groupHandler->getPermission($openCategory) < READ_ACCESS) {
            $openCategory = 0;
        }
        $page->setVar(
            'TREEDATA',
            json_encode($this->treeBuilder->buildPartialTree($openCategory))
        );
    }

    public function getPage() {
        return $this->page;
    }
}