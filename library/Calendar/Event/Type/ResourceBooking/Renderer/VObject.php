<?php

/**
 * This is the vcal renderer for ResourceBooking events
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type\ResourceBooking\Renderer;

use Calendar\Event\Render\VObjectTrait;

class VObject extends Base
{
    /**
     * Inherit default VObject formatting
     * render()
     */
    use VObjectTrait;

    /**
     * Delegate to parent (Base) rather than the trait's default null implementation.
     * PHP trait methods take precedence over inherited parent methods, so without this
     * override the trait's getExportType() would always return null.
     *
     * @return ?string
     */
    public function getExportType(): ?string
    {
        return parent::getExportType();
    }

    protected function getCategories(): string
    {
        return $this->getEvent()->getCategory();
    }
}
