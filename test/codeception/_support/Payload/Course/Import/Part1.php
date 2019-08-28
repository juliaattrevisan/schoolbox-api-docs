<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Course\Import;

use Payload\Request;

class CourseImport extends Request
{
    private $course_spec_id;
    private $class_page_id;
    private $unit_count;

    public function __construct()
    {
        $this->course_spec_id = '??';
        $this->class_page_id = '??';
        $this->unit_count=0;
        $this->setPage('/course/import/classes');
        $this->setReferer('/course/import/'.$this->course_spec_id.'/folder/0/0');
        $this->setFormParam('course_spec_id', $this->course_spec_id);
        $this->setFormParam('import_folders', $this->class_page_id);
        $this->setFormParam('component_id', '');
        $this->setFormParam('part-1', 1);
    }

    public function setCourseSpecId(int $course_spec_id)
    {
        $this->course_spec_id = $course_spec_id;
        $this->setReferer('/course/import/'.$this->course_spec_id.'/folder/0/0');
        $this->setFormParam('course_spec_id', $this->course_spec_id);
    }

    public function setClassPageId($class_page_id)
    {
        $this->class_page_id = $class_page_id;
        $this->setFormParam('import_folders', $this->class_page_id);
    }

    public function setImportUnits()
    {
        $this->setPage('/course/import/units');
        $this->deleteFormParams();
        $this->unit_count=0;
        $this->setFormParam('course_spec_id', $this->course_spec_id);
        $this->setFormParam('import_folders', $this->class_page_id);
        $this->setFormParam('component_id', '');
        $this->setFormParam('part-2', 1);
    }

    public function setImportAssessments()
    {
        $this->setPage('/course/import/activities');
        $this->deleteFormParams();
        $this->unit_count=0;
        $this->setFormParam('course_spec_id', $this->course_spec_id);
        $this->setFormParam('import_folders', $this->class_page_id);
        $this->setFormParam('component_id', '');
        $this->setFormParam('part-3', 1);
        $this->setFormParam('action', 'import');
    }

    public function addUnitImport(int $unitSpecId)
    {
        $import_timestamp=time();
        $this->setFormParam('unit['.$this->unit_count.'][unit_spec_id]', $unitSpecId);
        $this->setFormParam('unit['.$this->unit_count.'][unit_impl_id]', '');
        $this->setFormParam('unit['.$this->unit_count.'][import]', 'on');
        $this->setFormParam('unit['.$this->unit_count.'][open_date]', date('d/m/Y', strtotime('- 5 day', $import_timestamp)));
        $this->setFormParam('unit['.$this->unit_count.'][close_date]', date('d/m/Y', strtotime('+ 35 day', $import_timestamp)));
        $this->unit_count++;
    }

    public function addUnitSpec(int $unitSpecId)
    {
        $import_timestamp=time();
        $this->setFormParam('unit['.$unitSpecId.'][unit_spec_id]', $unitSpecId);
        $this->setFormParam('unit['.$unitSpecId.'][unit_impl_id]', '');
        $this->setFormParam('unit['.$unitSpecId.'][open_date]', date('d/m/Y', strtotime('- 5 day', $import_timestamp)));
        $this->setFormParam('unit['.$unitSpecId.'][close_date]', date('d/m/Y', strtotime('+ 35 day', $import_timestamp)));
    }

    public function addAssessmentImport(int $unitSpecId, int $asessment_id)
    {
        $import_timestamp=time();
        $this->setFormParam('unit['.$unitSpecId.'][a]['.$asessment_id.'][spec_id]', $asessment_id);
        $this->setFormParam('unit['.$unitSpecId.'][a]['.$asessment_id.'][impl_id]', '');
        $this->setFormParam('unit['.$unitSpecId.'][a]['.$asessment_id.'][checked]', 1);
        $this->setFormParam('unit['.$unitSpecId.'][a]['.$asessment_id.'][dates][publish]', date('d/m/Y g:ia', strtotime('- 3 day', $import_timestamp)));
        $this->setFormParam('unit['.$unitSpecId.'][a]['.$asessment_id.'][dates][due]', date('d/m/Y g:ia', strtotime('+ 15 day', $import_timestamp)));
    }

}
