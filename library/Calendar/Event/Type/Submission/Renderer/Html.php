<?php
/**
 * Calendar_Event_Type_Submission_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Calendar\Event\Type\Submission\Renderer;

class Html extends Base {


    public function render(): string
    {
        return $this->getTemplate()->render('calendar/type/submission.twig',
            array(  'event' => $this->getEvent(),
                    'renderer' => $this)
        );
    }

}
