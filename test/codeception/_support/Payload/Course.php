<?php


namespace Payload;

class Course extends Base
{
    public function __construct()
    {
        Base::__construct();
        $this->setPage('/course/new');
        $this->setReferer('/course/new');
        $this->setFormParam('name','');
        $this->setFormParam('parent','??');
        $this->setFormParam('curriculumCodes[]','');
        $this->setFormParam('part1',1);
    }

    public function setName(string $name)
    {
        $this->setFormParam('name',$name);
    }

    public function setParent(int $parent_folder_id)
    {
        $this->setFormParam('parent',$parent_folder_id);
    }

    public function setModifyCourseSpec(int $course_spec_id)
    {
        $this->setPage('/course/modify/'.$course_spec_id);
        $this->setReferer('/course/modify/'.$course_spec_id);
        $this->removeFormParam('part1',1);
        $this->setFormParam('newUnitName','');
        $this->setFormParam('submit','Save Course');
    }

    public function addUnitSpec(int $unitSpecId, string $unitName)
    {
        $this->setFormParam('unit['.$unitSpecId.'][name]', $unitName);
    }

}