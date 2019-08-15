<?php


namespace Payload;

class PastoralRecord extends Base
{

    const BLOCKED = '0';
    const ALLOW_PUBLIC = '1';
    const ALLOW_CONFIDENCIAL = '2';

    public static function getViewerPermissions(): array
    {
        return [
            'blocked' => self::BLOCKED,
            'public' => self::ALLOW_PUBLIC,
            'confidential' => self::ALLOW_CONFIDENCIAL
        ];
    }

    private $student_id;
    private $record_id;
    private $action;

    public function __construct()
    {
        Base::__construct();
        $this->action='insert';
        $this->setPage('/pastoral/record/'.$this->action);
        $this->setReferer('/pastoral/student/??/record/'.$this->action);
        $this->setFormParam('id', '');
        $this->setFormParam('student_id', '??');
        $this->setFormParam('heading', 'someHeading');


        $level_data= [
            'level' => 1,
            'campus[]' => 1
        ];
    }

    public function setHeading(string $heading)
    {
        $this->setFormParam('heading', $heading);
    }

    public function setStudentId(int $student_id)
    {
        $this->student_id=$student_id;
        if ($this->action==='insert')
        {
            $this->setReferer('/pastoral/student/'.$this->student_id.'/record/'.$this->action);
        } else {
            $this->setReferer('/pastoral/student/'.$this->student_id.'/record/'.$this->record_id.'/'.$this->action);
        }
        $this->setFormParam('student_id', $this->student_id);
    }

    public function setUpdateRecord(int $record_id)
    {
        $this->record_id=$record_id;
        $this->action='update';
        $this->setFormParam('id', $this->record_id);
        $this->setReferer('/pastoral/student/'.$this->student_id.'/record/'.$this->record_id.'/'.$this->action);
        $this->setPage('/pastoral/record/'.$this->record_id.'/'.$this->action);
    }

    public function setViewer(string $type, int $viewer_id, string $permissions)
    {
        $this->setFormParam($type.'['.$viewer_id.']', self::getViewerPermissions()[$permissions]);
    }

    public function removeViewer(string $type, int $viewer_id)
    {
        $this->removeFormParam($type.'['.$viewer_id.']');
    }

    public function setDupicateToUser($duplicate_target_id)
    {
        $this->setFormParam('duplicate_id[]', $duplicate_target_id);
    }

}