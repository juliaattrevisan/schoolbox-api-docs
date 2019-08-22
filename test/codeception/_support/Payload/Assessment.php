<?php


namespace Payload;

class Assessment extends Base
{
    private $capabilityCount;
    private $levelCount;
    private $contextType;
    private $contextId;
    private $assessmentType;
    public function __construct()
    {
        Base::__construct();

        $eventTimestamp        =time();
        $this->contextType     ='folders';
        $this->contextId       ='??';
        $this->assessmentType  ='dueWork';
        $this->capabilityCount =0;
        $this->levelCount      =0;
        $this->setPage('/learning/'.$this->contextType.'/'.$this->contextId.'/assessments');
        $this->setReferer( '/learning/'.$this->contextType.'/'.$this->contextId.'/assessments/new?type=='.$this->assessmentType);
        $this->setFormParam('contextType', $this->contextType);
        $this->setFormParam('contextId', $this->contextId);
        $this->setFormParam('folderId', '??');
        $this->setFormParam('assessmentType', $this->assessmentType);
        $this->setFormParam('title', 'someTitle');
        $this->setFormParam('description', '<p>This is a test assignment for automated tests</p>');
        $this->setFormParam('acceptOnline', 0);
        $this->setFormParam('publishDate', date('d/m/Y g:ia', strtotime('- 5 day', $eventTimestamp)));
        $this->setFormParam('openDate', date('d/m/Y g:ia', strtotime('- 1 day', $eventTimestamp)));
        $this->setFormParam('dueDate', date('d/m/Y g:ia', strtotime('+ 10 day', $eventTimestamp)));
        $this->setFormParam('closeDate', date('d/m/Y g:ia', strtotime('+ 12 day', $eventTimestamp)));
        $this->setFormParam('weight', 14);
        $this->setFormParam('self_reviewable', 'none');
        $this->setFormParam('visibility', 2);
        $this->setFormParam('markType', 1);
        $this->setFormParam('maxMark', '');
        $this->setFormParam('gradingScheme', 1);
        $this->setFormParam('resultset', 1);
        $this->setFormParam('formField', 'rb');
        $this->setFormParam('rb[0][level][0][id]', '');
        $this->setFormParam('rb[0][level][0][name]', '');
        $this->setFormParam('rb[0][level][1][id]', '');
        $this->setFormParam('rb[0][level][1][name]', '');
        $this->setFormParam('curriculumCodes', '');
        $this->setFormParam('part-2', 1);
    }
    /** Just a reminder for something I need to do when I get to it
    if ($assessmentType === 'project')
    {
    $data['summator']='weighted';
    }
     */

    /*

    */

    public function setContextType(string $contextType)
    {
        $this->contextType =$contextType;
        $this->setFormParam('contextType', $this->contextType);
        $this->setPage('/learning/'.$this->contextType.'/'.$this->contextId.'/assessments');
        $this->setReferer( '/learning/'.$this->contextType.'/'.$this->contextId.'/assessments/new?type=='.$this->assessmentType);
    }

    public function setContextId(int $contextId)
    {
        $this->contextId=$contextId;
        $this->setFormParam('contextId', $this->contextId);
        $this->setPage('/learning/'.$this->contextType.'/'.$this->contextId.'/assessments');
        $this->setReferer( '/learning/'.$this->contextType.'/'.$this->contextId.'/assessments/new?type=='.$this->assessmentType);
    }

    public function setAssessmentType (string $type)
    {
        $this->assessmentType=$type;
        $this->setReferer( '/learning/'.$this->contextType.'/'.$this->contextId.'/assessments/new?type=='.$this->assessmentType);
        $this->setFormParam('assessmentType', $this->assessmentType);
        if($type==='project')
        {
            $this->setFormParam('summator', 'weighted');
        } else {
            $this->removeFormParam('summator');
        }
    }

    public function setTitle(string $title)
    {
        $this->setFormParam('title', $title);
    }

    public function setFolderId(int $folderId)
    {
        $this->setFormParam('folderId', $folderId);
    }

    public function addRubricCapabitity(int $id, string $name){
        $this->setFormParam('rb[0][group]['.$this->capabilityCount.'][id]', $id);
        $this->setFormParam('rb[0][group]['.$this->capabilityCount.'][name]', $name);
        $this->capabilityCount++;
    }

    public function setRubricIndicator(int $id, int $groupId, string $name, bool $enabled){
        $this->setFormParam('rb[0][criterion]['.$id.'][id]', $id);
        $this->setFormParam('rb[0][criterion]['.$id.'][groupId]', $groupId);
        $this->setFormParam('rb[0][criterion]['.$id.'][on]', $enabled);
        $this->setFormParam('rb[0][criterion]['.$id.'][name]', $name);
    }

    public function addRubricLevel(string $id, string $name){
        $this->setFormParam('rb[0][level]['.$this->levelCount.'][id]', $id);
        $this->setFormParam('rb[0][level]['.$this->levelCount.'][name]', $name);
        $this->levelCount++;
    }

    public function setRubricDescription(string $id, string $indicatorId, int $level, string $text, string $points){
        $this->setFormParam('rb[0][descriptor]['.$indicatorId.']['.$level.'][id]', $id);
        $this->setFormParam('rb[0][descriptor]['.$indicatorId.']['.$level.'][text]', $text);
        $this->setFormParam('rb[0][descriptor]['.$indicatorId.']['.$level.'][points]', $points);
    }
}
