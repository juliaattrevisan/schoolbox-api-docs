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
use Schoolbox\Calendar\EventRecurrenceMapper;

class Create extends Request
{
    use PostTrait;

    /**
     * @param int $userId
     * @param int $authorId
     */
    public function __construct(int $userId, int $authorId)
    {
        $eventTimestamp = time();
        $eventDate      = date('d/m/Y', $eventTimestamp);

        $this->setReferer("/calendar/modify.php");

        $this->setFormParam('id', '');
        $this->setFormParam('userId', $userId);
        $this->setFormParam('authorId', $authorId);
        $this->setFormParam('allday', 1);
        $this->setFormParam('return', '/calendar/full/' . date('d-m-Y', $eventTimestamp) . '/' . $userId);
        $this->setFormParam('efficacy', EventRecurrenceMapper::EFFICACY_SELF);
        $this->setFormParam('action', 'Add');
        $this->setFormParam('title', '');
        $this->setFormParam('startDate', $eventDate);
        $this->setFormParam('endDate', $eventDate);
        $this->setFormParam('repeat', '');
        $this->setFormParam('repeat_until', '');
        $this->setFormParam('type', '');
        $this->setFormParam('location', 'Automated test location');
        $this->setFormParam('detail', '<p>Automated test description</p>');
        $this->setFormParam('publishDate', date('d/m/Y g:ia', strtotime('- 1 day', $eventTimestamp)));
        $this->setFormParam('level', Calendar\Event::ACCESS_PERSONAL);
        $this->setFormParam('campus[0]', 1);
        $this->setFormParam('Add', 'Add Event');
    }

    public function setTitle(string $title): void
    {
        $this->setFormParam('title', $title);
    }

    public function getUrl(): string
    {
        return "/calendar/modifyForm.php";
    }
}
