<?php


namespace Payload;


class CalendarEvent extends Base
{

    const PERSONAL_EVENT = '1';
    const GROUP_EVENT = '2';
    const CAMPUS_EVENT = '3';

    public static function getEventTypes(): array
    {
        return [
            'personal' => self::PERSONAL_EVENT,
            'group' => self::GROUP_EVENT,
            'campus' => self::CAMPUS_EVENT
        ];
    }

    private $eventTimestamp;
    private $eventDate;
    public function __construct()
    {
        Base::__construct();

        $this->eventTimestamp=time();
        $this->eventDate=date('d/m/Y', $this->eventTimestamp);
        $this->setPage('/calendar/modifyForm.php');
        $this->setReferer( '/calendar/modify.php');
        $this->setFormParam('id', '');
        $this->setFormParam('userId', '??');
        $this->setFormParam('authorId', '??');
        $this->setFormParam('allday', 1);
        $this->setFormParam('return', '/calendar/full/'.date('d-m-Y', $this->eventTimestamp).'/'.'??');
        $this->setFormParam('efficacy', 'self');
        $this->setFormParam('action', 'Add');
        $this->setFormParam('title', 'someTitle');
        $this->setFormParam('startDate', $this->eventDate);
        $this->setFormParam('endDate', $this->eventDate);
        $this->setFormParam('repeat', null);
        $this->setFormParam('repeat_until', null);
        $this->setFormParam('type', 1);
        $this->setFormParam('location', 'Automated test location');
        $this->setFormParam('detail', '<p>Automated test description</p>');
        $this->setFormParam('publishDate', date('d/m/Y g:ia', strtotime('- 1 day', $this->eventTimestamp)));
    }

    public function setUserID(int $user_id)
    {
        $this->setFormParam('return', '/calendar/full/'.date('d-m-Y', $this->eventTimestamp).'/'.$user_id);
        $this->setFormParam('userId', $user_id);
        $this->setFormParam('authorId', $user_id);
    }

    public function setTitle(string $title)
    {
        $this->setFormParam('title', $title);
    }
    
}
