<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_QuicklinksController extends Component_Dashboard_ControllerAbstract {

    private $quicklinks;

    public function execute () {

        // data setup is based on the user that's about to see it
        $groupHandler = new Group();

        $this->quicklinks['all'] = array(
            'calendar' => array('/calendar', 'Calendar'),
            'news' => array('/news', 'News'),
        );

        if($groupHandler->isStaff()) {
            $this->quicklinks['staff'] = array(
                'my-timetable' => array('/timetable', 'My Timetable'),
                'synergetic' => array('http://synergetic.alrs:7007/', 'Synergetic Web Application'),
            );

        }
        elseif($groupHandler->isParent()) {
            $this->quicklinks['parents'] = array(
                'synergetic-parent-portal' => array('http://synergetic.alrs:7008/', 'Synergetic Parent Portal'),
                'evi-parent-homepage' => array('#', 'eVI Parent Homepage'),
            );

        }
        elseif($groupHandler->isStudent()) {
            $this->quicklinks['students'] = array(
                'my-timetable' => array('/timetable', 'My Timetable'),
                'eportfolio' => array('#', 'My ePortfolio'),
            );
        }
        else {
            return false;
        }

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'quicklinks') ) {
            return false;
        }

        $this->setBlock($templateHandle, 'qlinks', 'qlinks_block');
        $this->setBlock('qlinks', 'qlinks_item', 'qlinks_item_block');


        foreach($this->quicklinks as $groupName => $items) {
            foreach($items as $class => $item) {
                $this->setVar(array(
                    'GROUP_NAME' => $groupName,
                    'QUICKLINK_CLASS' => $class,
                    'QUICKLINK_HREF' => $item[0],
                    'QUICKLINK_LABEL' => $item[1],
                ));
                $this->parse('qlinks_item_block', 'qlinks_item', true);
            }
            $this->parse('qlinks_block', 'qlinks', false);
        }

        return true;
    }

}