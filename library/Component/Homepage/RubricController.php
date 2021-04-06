<?php

/**
 * Rubric view and edit component for teachers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper;
use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_RubricController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var Model[]
     */
    protected $criteria = [];

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
        }

        // get rubric details
        $this->criteria = Mapper::getInstance()->findForFolder($this->getContext()->getId());
        return !(count($this->criteria) === 0 && ($this->getContentPermission() < UserAccess::ACCESS_WRITE));
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

        $this->setBlock($templateHandle, 'rubric', 'rubric_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if (count($this->criteria) === 0) {
            $this->setVar(
                [
                    'rubric_block'      => '',
                    'empty_read_block'  => '',
                    'empty_write_block' => '',
                ]
            );

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setVar('empty_state_block', '');

            $this->setBlock('rubric', 'rubric_criterion', 'rubric_criterion_block');
            $this->setBlock('rubric_criterion', 'rubric_descriptor', 'rubric_descriptor_block');
            $this->setBlock('rubric', 'modify_rubric', 'modify_rubric_block');

            $this->setVar(
                [
                    'rubric_criterion_block' => '',
                    'modify_rubric_block'    => '',
                ]
            );

            $i = 0;

            /* Create group structure */
            /** @var Model[][] $criteriaList */
            $criteriaList = [];

            foreach ($this->criteria as $crit) {
                $criteriaList[$crit->getCapability()->getName()][] = $crit;
            }

            foreach ($criteriaList as $criterion_group => $criterions) {
                $this->setVar(
                    [
                        'CRITERION_GROUP'         => Scrub::text($criterion_group),
                        'rubric_descriptor_block' => '',
                    ]
                );

                foreach ($criterions as $crit) {
                    if (trim($crit->getName()) !== '') {
                        $this->setVar('CRITERION_NAME', Scrub::text($crit->getName()));
                        $this->parse('rubric_descriptor_block', 'rubric_descriptor', true);
                    }
                }

                $this->parse('rubric_criterion_block', 'rubric_criterion', true);
            }

            if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                $this->parse('modify_rubric_block', 'modify_rubric');
            } else {
                $this->setVar('modify_rubric_block', '');
            }

            $this->parse('rubric_block', 'rubric');
        }

        return true;
    }
}
