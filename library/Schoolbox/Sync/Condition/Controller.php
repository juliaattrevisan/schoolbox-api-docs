<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Condition;

use Alaress\Web\Controller\Base;
use Schoolbox\Sync\Grammar\FieldFactory;
use Schoolbox\Sync\Rule;
use Schoolbox\Sync\Condition;
use Schoolbox\Sync\Condition\Fragment\Fragment;
use Schoolbox\Sync\Condition\Fragment\Factory as ConditionFragmentFactory;

class Controller extends Base {

    /**
     * A meta field for various Condition-specific UI
     * so that when you load a Ruleset UI, all Conditions are
     * impossible to accidentally break. You must actively unlock
     * each Condition you want to edit.
     *
     * meta->disabled is the attribute on input, select, textarea elements
     * meta->lock->status is a flag for whether the Condition is locked (1 yes, 0 no)
     * meta->lock->icon is the action button icon- class to start with
     *
     * Should be set to "disabled", "1" and "locked" by default
     */
    private $meta = [
        'disabled' => '',
        'lock' => [
            'status' => 0,
            'icon' => 'locked',
        ],
        'status' => 'Condition not yet saved'
    ];

    /**
     * Forge a data structure to feed into twig template,
     * so that new Conditions can be injected directly into
     * the ui after being received via ajax
     *
     * @param $params
     */
    public function add($params) {

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
                    $newCondition->getId() => $this->getMeta()
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
    }

    /* Getters/Setters
     */

    /**
     * Set meta
     *
     * @param mixed $meta
     */
    private function setMeta($meta)
    {
        $this->meta = $meta;
    }

    /**
     * Get meta
     *
     * @return mixed
     */
    private function getMeta()
    {
        return $this->meta;
    }
}