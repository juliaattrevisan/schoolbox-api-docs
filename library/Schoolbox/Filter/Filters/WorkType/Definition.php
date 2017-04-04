<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WorkType;


use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\WorkType;

class Definition extends AbstractFilterDefinition
{

    /**
     * @var GenericCollection|WorkType\Model[]
     */
    private $workTypes;


    /**
     * @param GenericCollection|WorkType\Model[] $workTypes
     */
    public function setWorkTypes(GenericCollection $workTypes)
    {
        $this->workTypes = $workTypes;
    }

    /**
     * @inheritdoc
     */
    protected function getTemplateFile()
    {
        return 'workType.twig';
    }


    /**
     * @inheritdoc
     */
    public function toPanelContext($page, User $user)
    {
        // TODO: Implement method.
    }


    /**
     * @inheritdoc
     */
    public function getOptions()
    {
        // TODO: Implement  method.
    }


    /**
     * @inheritdoc
     */
    public function getAvailableOptions()
    {
        // TODO: Implement method.
    }


    /**
     * @inheritdoc
     */
    public function getPages()
    {
        // TODO: Implement method.
    }


    /**
     * @inheritdoc
     */
    public function getAbstractKeys()
    {
        // TODO: Implement  method.
    }
}
