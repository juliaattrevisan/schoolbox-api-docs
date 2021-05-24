<?php

/**
 * This is the vcal renderer for submission events
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type\Submission\Renderer;

use Calendar\Event\Render\VObjectTrait;

/**
 * Class VObject
 *
 * @package Calendar\Event\Type\Submission\Renderer
 */
class VObject extends Base
{
    /**
     * Inherit default VObject formatting method & properties
     * vCalendar
     * render()
     */
    use VObjectTrait;


    /**
     * @return string
     */
    protected function getCategories(): string
    {
        return $this->getEvent()->getIsAssessment() ? 'Quiz' : 'Due Work';
    }
}
