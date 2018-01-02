<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Poll extends Component_Config_Abstract
{

    protected $is_shown_on_template = false;

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        $pollHandler = new Poll();

        // has an internal block for the select element
        $pollList = $this->getContext()
            ? $pollHandler->getAllByCategory($this->getContext()->getId())
            : new \Schoolbox\Collection();

        $page->setBlock($keyblock, 'poll_select', 'poll_select_block');
        $page->setVar('poll_select_block', '');

        if (is_array($pollList)) {
            foreach ($pollList as $poll) {
                $page->setVar(array(
                    'COMPONENT_CONFIG_POLLSELECT_NAME' 		=> TextFormatter::cutString(Scrub::text($poll['question']), 30),
                    'COMPONENT_CONFIG_POLLSELECT_VALUE' 	=> $poll['id'],
                    'COMPONENT_CONFIG_POLLSELECT_SELECTED'	=> $poll['id'] == $configValue ? 'selected="selected"' : '',
                ));

                $page->parse("poll_select_block", "poll_select", true);
            }
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_POLLSELECT_NAME' 		=> Scrub::text(' (none) '),
                'COMPONENT_CONFIG_POLLSELECT_VALUE' 	=> '0',
                'COMPONENT_CONFIG_POLLSELECT_SELECTED'	=> 'disabled="disabled"',
            ));

            $page->parse("poll_select_block", "poll_select", true);
        }

    }
}
