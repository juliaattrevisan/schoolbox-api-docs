<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\Submission\Renderer;

use \Calendar\Event\Type\RendererBase;

class Base extends RendererBase
{

    /**
     * Hide submissions from Students and Parents if not yet published
     *
     * @return bool
     */
    public function isVisible() {
        $alwaysVisible = $this->getViewer()->isSuperuser() || $this->getViewer()->getRole()->isStaff();
        if(!$alwaysVisible){
            return $this->getEvent()->getPublishDate()->lte(new \Alaress_Date_Format());
        }
        return true;
    }

    /**
     * Helper function to determine if person viewing assessment event can edit it, this should probably be on the
     * event itself but its not so this will help
     * @return bool
     */
    public function isEditable() {
        if ($this->getViewer()->getRole()->isStaff()) {
            return true;
        } else {
            return false;
        }
    }
} 