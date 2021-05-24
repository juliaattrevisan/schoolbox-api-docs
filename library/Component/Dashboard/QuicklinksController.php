<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

class Component_Dashboard_QuicklinksController extends Component_Dashboard_ControllerAbstract
{

    private $quicklinks;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {

        // data setup is based on the user that's about to see it
        $groupHandler = new Group();

        $this->quicklinks['all'] = array(
            'calendar' => array('/calendar', 'Calendar'),
            'news' => array('/news', 'News'),
        );

        if ($groupHandler->isStaff()) {
            $this->quicklinks['staff'] = array(
                'my-timetable' => array('/timetable', 'My Timetable'),
                'synergetic' => array('/synergetic', 'Synergetic Web Application'),
            );
        } elseif ($groupHandler->isParent()) {
            $this->quicklinks['parents'] = array(
                'synergetic-parent-portal' => array('/synergetic', 'Synergetic Parent Portal'),
                'evi-parent-homepage' => array('#', 'eVI Parent Homepage'),
            );
        } elseif ($groupHandler->isStudent()) {
            $this->quicklinks['students'] = array(
                'my-timetable' => array('/timetable', 'My Timetable'),
                'eportfolio' => array('#', 'My ePortfolio'),
            );
        } else {
            return false;
        }

        return true;
    }

    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'quicklinks')) {
            return false;
        }

        $this->setBlock($templateHandle, 'qlinks_item', 'qlinks_item_block');

        foreach ($this->quicklinks as $groupName => $items) {
            foreach ($items as $class => $item) {
                $this->setVar(array(
                    'GROUP_NAME' => $groupName,
                    'QUICKLINK_CLASS' => $class,
                    'QUICKLINK_HREF' => $item[0],
                    'QUICKLINK_LABEL' => $item[1],
                ));
                $this->parse('qlinks_item_block', 'qlinks_item', true);
            }
        }

        return true;
    }
}
