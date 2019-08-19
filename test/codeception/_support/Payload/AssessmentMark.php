<?php


namespace Payload;


class AssessmentMark extends Base
{

    private $assessmentId;
    private $submitting_student_id;
    private $submissionId;
    public function __construct()
    {
        Base::__construct();
        $this->assessmentId='??';
        $this->submitting_student_id='??';
        $this->submissionId='??';
        $this->setPage('/learning/assessments/'.$this->assessmentId .'/'.$this->submitting_student_id.'/'.$this->submissionId.'/feedback');
        $this->setReferer('/learning/assessments/'.$this->assessmentId .'/'.$this->submitting_student_id.'/responses');
        $this->setFormParam('responseId', $this->submissionId);
        $this->setFormParam('fromInlineGrader', true);
        $this->setFormParam('mark', '75');
        $this->setFormParam('comment', 'Some Feedback for Student');
        $this->setFormParam('publish', '1');
    }

    public function setAssessmentId(int $assessmentId)
    {
        $this->assessmentId=$assessmentId;
        $this->setPage('/learning/assessments/'.$this->assessmentId .'/'.$this->submitting_student_id.'/'.$this->submissionId.'/feedback');
        $this->setReferer('/learning/assessments/'.$this->assessmentId .'/'.$this->submitting_student_id.'/responses');
    }

    public function setSubmittingStudentId(int $student_id)
    {
        $this->submitting_student_id=$student_id;
        $this->setPage('/learning/assessments/'.$this->assessmentId .'/'.$this->submitting_student_id.'/'.$this->submissionId.'/feedback');
        $this->setReferer('/learning/assessments/'.$this->assessmentId .'/'.$this->submitting_student_id.'/responses');
    }

    public function setSubmissionId(int $submissionId)
    {
        $this->submissionId=$submissionId;
        $this->setPage('/learning/assessments/'.$this->assessmentId .'/'.$this->submitting_student_id.'/'.$this->submissionId.'/feedback');
        $this->setFormParam('responseId', $this->submissionId);
    }

    public function getAssessmentId()
    {
        return $this->assessmentId;
    }

    public function getSubmissionId()
    {
        return $this->submissionId;
    }

    public function addMarkRubricDescriptorSelect(int $descriptorId)
    {
        $this->setFormParam('rubric[descriptors]['.$descriptorId.'][selected]', '75');
    }

}