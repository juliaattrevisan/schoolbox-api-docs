<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Calendar\Event;

use Payload\PostTrait;
use Payload\Request;
use Schoolbox\Calendar;

class Update extends Request
{
    use PostTrait;

    public function __construct(Calendar\Event $event)
    {
        $this->setReferer("/calendar/modify.php?eventID=7527&returnTo=calendar");

        $this->setFormParam('id', $event->getId());
        $this->setFormParam('userId', $event->getAuthor()->getId());
        $this->setFormParam('authorId', $event->getAuthor()->getId());
        $this->setFormParam('allday', $event->getAllDay());
        $this->setFormParam('return', '/calendar/' . date('d-m-Y', $event->getStartDateTime()->getUnixTimeStamp()));
        $this->setFormParam('efficacy', $event->inSeries() ? Calendar\EventRecurrenceMapper::EFFICACY_SERIES : Calendar\EventRecurrenceMapper::EFFICACY_SELF);
        $this->setFormParam('action', 'Update');
        $this->setFormParam('title', $event->getTitle());
        $this->setFormParam('startDate', $event->getStartDateTime()->getUsingFormat('d/m/Y'));
        $this->setFormParam('endDate', $event->getEndDateTime()->getUsingFormat('d/m/Y'));
        $this->setFormParam('repeat', '');
        $this->setFormParam('repeat_until', '');
        $this->setFormParam('type', $event->getType()->getId());
        $this->setFormParam('location', $event->getLocation());
        $this->setFormParam('detail', $event->getDetail());
        $this->setFormParam('publishDate', '');
        $this->setFormParam('level', Calendar\Event::ACCESS_PERSONAL);
        $this->setFormParam('Update', 'Update Event');
    }

    public function getUrl(): string
    {
        return "/calendar/modifyForm.php";
    }
}


