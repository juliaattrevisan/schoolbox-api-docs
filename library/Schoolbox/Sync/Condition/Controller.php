<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Condition;

use Alaress\Web\Controller\Base;
use Schoolbox\Sync\Grammar\FieldFactory;
use Schoolbox\Sync\Rule;
use Schoolbox\Sync\Condition;
use Schoolbox\Sync\Condition\Fragment\Factory as ConditionFragmentFactory;

/**
 * Class Controller
 *
 * @package Schoolbox\Sync\Condition
 */
class Controller extends Base
{

    /**
     * A meta field for various Condition-specific UI
     */
    private $meta = [
        'status' => 'Condition not yet saved'
    ];


    /**
     * Forge a data structure to feed into twig template,
     * so that new Conditions can be injected directly into
     * the ui after being received via ajax
     *
     * @param array $params
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function add($params)
    {

        /* Spawn Rule
         */
        $rule = Rule\Mapper::getInstance()->findById($params['ruleId']);

        $newCondition = Condition\Mapper::getInstance()->createModel();
        $newCondition
            ->setId('new')
            ->setRule($rule)
            ->setConditionType(Condition\Type\Mapper::getInstance()->findById($params['conditionType']));

        $ffactory = new FieldFactory();

        /* Determine which Condition Fragments to load initially
         * It tells frontend which templates to use for Conditions.
         */
        $conditionFragmentFactory = new ConditionFragmentFactory();
        $subfragment = $conditionFragmentFactory->produceType($newCondition->getConditionType());

        $data = [
            'allNDSField'      => $ffactory->buildMap($rule->getRoleType()),
            'meta'             => [
                'condition' => [
                    $newCondition->getId() => $this->meta
                ]
            ],
            'currentCondition' => $newCondition,

            'allConditionStructures' => [
                'fragments' => [
                    $newCondition->getId() => [
                        'subfragments' => [
                            'fragment' => $subfragment,
                        ]
                    ]
                ]
            ],
        ];

        $this->getResponse()->content->set([
            'template' => '@upsCondition/container.twig',
            'data'     => $data,
            'format'   => 'nochrome'
        ]);
    }


    /**
     * Delete a Condition asynchronously
     *
     * @return bool
     */
    public function delete()
    {
        $conditionId = $this->getRequest()->post->get('conditionId');
        $deleteCount = Condition\Mapper::getInstance()->deleteByColumn('id', $conditionId);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $deleteCount > 0
        ]);

        return true;
    }
}
