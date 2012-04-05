<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Resource_Browser {

    private $page;

    public function __construct(SitePage $page) {
        $this->page = $page;
    }

    public function render($templateHandle) {
        $page = $this->getPage();
        $page->setFile($templateHandle, 'resource_browser.html');
        $page->setBlock($templateHandle, 'category_list', 'category_list_block');


        $groupHandler = new Group;
        $categoryHandler = new Category;



        $openCategory = (int)$_SESSION['resourcesID'];

        // check permissions on selected category
        if ($groupHandler->getPermission($openCategory) < READ_ACCESS) {
            $openCategory = 0;
        }


        // get parentIDs from openCategory to 0
        $parentList[] = $parentID = $openCategory;
        $i = 0;
        while ($parentID > 0) {
            if ($i++ > 100) {
                break;
            }
            $parentList[] = $parentID = (int)$categoryHandler->getParent($parentID);
        }

        $output = array(
            'WRITE' => $groupHandler->getPermission(0) >= WRITE_ACCESS ? 'write' : '',
            'FULL' => $groupHandler->getPermission(0) >= FULL_ACCESS ? 'full' : '',
            'OPEN' => $openCategory,
            'HTTP_HOST' => $_SERVER['HTTP_HOST'],
        );
        $page->setVar($output);


        // output folders, column 1
        $categoryList = $categoryHandler->getCategoryNode($openCategory);
        if (is_array($categoryList)) {

            foreach ($categoryList as $category) {
                // get permissions
                if (!isset($permission[$category['id']])) {
                    $permission[$category['id']] = $groupHandler->getPermission($category['id']);
                }

                // limit permission by parent
                //if (isset($permission[$category['parent']]) && $permission[$category['id']] > $permission[$category['parent']])
                //	$permission[$category['id']] = $permission[$category['parent']];

                // check permission
                if ($permission[$category['id']] >= READ_ACCESS) {

                    $class = array();

                    // set parent category
                    $class[] = "child-of-node-{$category['parent']}";

                    /* add plus sign to parent nodes */
                    if ($category['isParent'] == '1') {
                        $class[] = in_array($category['id'], $parentList) ? 'expanded loaded' : 'collapsed';
                    }

                    /* select default dir */
                    if ($category['id'] == $openCategory) {
                        $class[] = 'selected';
                    }

                    /* mark homepages */
                    if ($category['template'] > 0) {
                        $class[] = "homepage";
                    }

                    /* mark write access */
                    if ($permission[$category['id']] == WRITE_ACCESS) {
                        $class[] = "write";
                    }

                    /* mark full access*/
                    if ($permission[$category['id']] == FULL_ACCESS) {
                        $class[] = "full";
                    }

                    /* append code to name */
                    $name = Scrub::text($category['name']);
                    if ($category['code'] != '' && $category['code'] != $category['name']) {
                        $name .= ' (' . Scrub::text($category['code']) . ')';
                    }

                    $output = array(
                        'CATEGORY_ID' => $category['id'],
                        'CATEGORY_NAME' => htmlspecialchars_decode($name),
                        'CLASS' => implode(" ", $class),
                    );
                    $page->setVar($output);
                    $page->parse('category_list_block', 'category_list', true);
                }
            }

            /* If no permission to folders handle blank block */
            if (!$output['CATEGORY_ID']) {
                $page->setVar('category_list_block', '');
            }

        }
        else {
            $page->setVar('category_list_block', '');
        }

    }





    public function getPage() {
        return $this->page;
    }
}