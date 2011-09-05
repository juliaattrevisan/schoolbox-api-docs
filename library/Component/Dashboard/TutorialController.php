<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_TutorialController extends Component_ControllerAbstract {

    protected $group;

    protected $event;

    protected $eventList;

    public function __construct (Component_Instance $instance, User $user, Template $template, Group $group) {
        parent::__construct($instance, $user, $template);

        $this->group = $group;
        $this->group->setService(TUTORIAL_SERVICE);

        /* TODO: Integrate this library in to the main library folder */
        require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
        $this->event = $event;
    }

    public function execute () {
        $this->eventList = $this->event->getHome();

        $this->uncompleteEventList = $this->event->getUncompleted(0, strtotime("-1 day"), $_SESSION['id']);
    }

    public function render ($templateHandle) {
        $this->setBlock($templateHandle, 'has_tutorials', 'has_tutorials_block');
        $this->setBlock('has_tutorials', 'tutorial_list', 'tutorial_list_block');
        $this->setBlock('tutorial_list', 'modify_tutorial', 'modify_tutorial_block');

        $this->setBlock($templateHandle, 'has_unmarked', 'has_unmarked_block');
        $this->setBlock('has_unmarked', 'unmarked_list', 'unmarked_list_block');

        if ( is_array($this->eventList) ) {
            $j = 0;
            foreach ( $this->eventList as $event ) {
                $tutorialDate = new Alaress_Date_Format( $event['date'], 'U' );

                $output = array(
                    'TUTORIAL_ID' => $event['eventID'], 'TUTORIAL_DATE' => $tutorialDate->getLong(),
                    'TUTORIAL_START' => $event['start'], 'TUTORIAL_FINISH' => $event['finish'],
                    'TUTORIAL_NAME' => Scrub::text($event['name']), 'TUTORIAL_LOCATION' => Scrub::text($event['location']),
                    'TUTORIAL_CANCELLED' => $event['cancelled'] == 'true' ? 'cancelled' : '',
                    'ON_OFF' => ++$j % 2 ? '' : 'dark',

                );

                $this->setVar($output);

                $this->setVar('modify_tutorial_block', '');

                if ( $this->group->getPermission(0) >= FULL_ACCESS || ( $this->group->getPermission(0) == WRITE_ACCESS && in_array($_SESSION['id'], array($event['tutorID_1'], $event['tutoriID_2'])) )) {
                    $this->parse('modify_tutorial_block', 'modify_tutorial');
                }

                $this->parse('tutorial_list_block', 'tutorial_list', true);
            }

            $this->parse('has_tutorials_block', 'has_tutorials');
        }
        else {
            $this->setVar('has_tutorials_block', '');
        }

        if ( is_array($this->uncompleteEventList) ) {
            $j = 0;
            foreach ( $this->uncompleteEventList as $event ) {
                $tutorialDate = new Alaress_Date_Format( $event['date'], 'U' );

                $weekDate = new Alaress_Date_Format( $event['date'], 'U' );

                $output = array(
                    'TUTORIAL_ID' => $event['eventID'],
                    'TUTORIAL_WEEK' => date('YW', $weekDate->getUnixTimestamp()) - 1,
                    'TUTORIAL_DATE' => $tutorialDate->getShort(), 'TUTORIAL_START' => $event['start'],
                    'TUTORIAL_FINISH' => $event['finish'], 'TUTORIAL_NAME' => Scrub::text($event['name']),
                    'TUTORIAL_LOCATION' => Scrub::text($event['location']),
                    'TUTORIAL_CANCELLED' => $event['cancelled'] == 'true' ? 'cancelled' : '',
                    'ON_OFF' => ++$j % 2 ? 'dark' : '',
                );
                $this->setVar($output);

                $this->parse('unmarked_list_block', 'unmarked_list', true);
            }
            $this->parse('has_unmarked_block', 'has_unmarked');

        }
        else {
            $this->setVar('has_unmarked_block', '');
        }

        return true;
    }

}