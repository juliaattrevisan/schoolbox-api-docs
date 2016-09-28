<?php
/**
 * Rubric view and edit component for teachers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_RubricController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var DueWork_Rubric_Handler
     */
    protected $rubric_handler;

    /**
     * @var array
     */
    protected $criteria = array();

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        // get rubric details
        $this->criteria = CriterionMapper::getInstance()->findByColumnList(
            array(
                'parent_id'   => $this->getContext()->getId(),
                'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
            ),
            array('order' => array('grouping' => 'ASC', 'name' => 'ASC'))
        );
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'rubric')) {
            return false;
        }

        $this->parseConfig();

        // Dump out block stuff
        $this->setBlock($templateHandle, 'rubric_criterion', 'rubric_criterion_block');
        $this->setBlock('rubric_criterion', 'rubric_descriptor', 'rubric_descriptor_block');
        $this->setBlock($templateHandle, 'modify_rubric', 'modify_rubric_block');
        $this->setVar('rubric_criterion_block', '');
        $this->setVar('modify_rubric_block', '');
        $i = 0;

        /* Create group structure */
        $criteriaList = array();

        foreach ($this->criteria as $crit) {
            $criteriaList[$crit->getGrouping()][] = $crit;
        }

        foreach ($criteriaList as $criterion_group => $criterions) {
            $this->setVar('CRITERION_GROUP', Scrub::text($criterion_group));
            $this->setVar('DARK', ($i++ % 2 === 1 ? 'dark' : ''));
            $this->setVar('rubric_descriptor_block', '');
            foreach ($criterions as $crit) {
                $this->setVar('CRITERION_NAME', Scrub::text($crit->getName()));
                $this->parse('rubric_descriptor_block', 'rubric_descriptor', true);
            }
            $this->parse('rubric_criterion_block', 'rubric_criterion', true);
        }

        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $this->parse('modify_rubric_block', 'modify_rubric');
        } else {
            $this->setVar('modify_rubric_block', '');
        }

        return true;
    }
}
