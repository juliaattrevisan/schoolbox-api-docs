<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Folder\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;

class Renderer extends Handler {

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'category';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Folders';

    /**
     * Render
     */
    public function render() {
        $categoryStd = new \Category();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('category_t', 'resource/folder/search/results.html');
            $this->getPage()->setBlock('category_t', 'category', 'category_block');
            $this->getPage()->setBlock('category', 'modify', 'modify_block');
            $this->getPage()->setVar(array(
                'CATEGORY_DISP_COUNT' => count($this->getResult($this->getType())),
                'CATEGORY_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $category) {
                foreach ($categoryStd->breadcrumb($category['id']) as $i => $breadcrumb) {
                    $category['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
                }

                $this->getPage()->setVar(array(
                    'CATEGORY_ID' => $category['id'],
                    'CATEGORY_NAME' => $category['code'] ? Scrub::text($category['name']) . '(' . $category['code'] . ')' : Scrub::text($category['name']),
                    'CATEGORY_CODE' => $category['code'],
                    'CATEGORY_BREADCRUMB' => $category['breadcrumb'],
                    'CATEGORY_COUNT' => $this->getCount($this->getType()),
                    'CATEGORY_TYPE' => ($category['template'] ? 'home' : 'folder'),
                ));

                if ($category['perms_type'] < 2 && $category['userAccess_type'] < 2)
                    $this->getPage()->setVar('modify_block');
                else
                    $this->getPage()->parse('modify_block', 'modify');

                $this->getPage()->parse('category_block', 'category', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'category_t', true);
        }
        else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}