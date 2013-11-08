<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Poll extends Component_Config_Abstract {

	protected $is_shown_on_template = false;

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        $pollHandler = new Poll();

        // has an internal block for the select element
        $pollList = $pollHandler->getAllByCategory($this->getCategory('id'));

        $page->setBlock($keyblock, 'poll_select', 'poll_select_block');
        $page->setVar('poll_select_block', '');

        if (is_array($pollList)) {
            foreach ($pollList as $poll) {
                $page->setVar(array(
                    'COMPONENT_CONFIG_POLLSELECT_NAME' 		=> Scrub::text($poll['title']),
                    'COMPONENT_CONFIG_POLLSELECT_VALUE' 	=> $poll['pollID'],
                    'COMPONENT_CONFIG_POLLSELECT_SELECTED'	=> $poll['pollID'] == $configValue ? 'selected="selected"' : '',
                ));

                $page->parse("poll_select_block", "poll_select", true);
            }
        }
        else {
            $page->setVar(array(
                'COMPONENT_CONFIG_POLLSELECT_NAME' 		=> Scrub::text(' (none) '),
                'COMPONENT_CONFIG_POLLSELECT_VALUE' 	=> '0',
                'COMPONENT_CONFIG_POLLSELECT_SELECTED'	=> 'disabled="disabled"',
            ));

            $page->parse("poll_select_block", "poll_select", true);
        }

    }

}
