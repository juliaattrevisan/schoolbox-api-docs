<?php
/**
 * Calendar_Event_Type_Submission_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Calendar\Event\Type\Submission\Renderer;

class Html extends Base {


    public function render()
    {
        if(!$this->isVisible()){
            return '';
        }

        return $this->getTemplate()->render('calendar/type/submission.twig',
            array(  'event' => $this->getEvent(),
                    'renderer' => $this)
        );
    }

}
