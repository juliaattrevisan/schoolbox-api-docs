<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_CommentsSize extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'comments_sizes', 'comments_sizes_block');
        $page->setVar('comments_sizes_block', '');

        for ($i = 0; $i <= 10; $i++) {
            $page->setVar('COMPONENT_CONFIG_COMMENTSSIZE_NAME', $i == 0 ? 'All' : $i);
            $page->setVar('COMPONENT_CONFIG_COMMENTSSIZE_VALUE', $i);
            $page->setVar('COMPONENT_CONFIG_COMMENTSSIZE_SELECTED', $i == $configValue ? ' selected="selected"' : '');
            $page->parse('comments_sizes_block', 'comments_sizes', true);
        }

    }
}
