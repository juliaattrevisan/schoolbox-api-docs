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

class Controller extends Base {
    const CONDITION_GENERAL = 1;
    const CONDITION_USER = 2;

    /**
     * Forge a data structure to feed into twig template,
     * so that new Conditions can be injected directly into
     * the ui after being received via ajax
     *
     * @param $params
     */
    public function add($params) {

        /* A meta field for various Condition-specific UI
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
        $meta = [
            'disabled' => '',
            'lock' => [
                'status' => 0,
                'icon' => 'locked',
            ],
            'status' => 'Condition not yet saved'
        ];

        $rule = Rule\Mapper::getInstance()->findById($params['ruleId']);

        $newCondition = Condition\Mapper::getInstance()->createModel();

        $newCondition->setRule($rule)->setConditionType(Condition\Type\Mapper::getInstance()->findById($params['conditionType']));

        Condition\Mapper::getInstance()->create($newCondition);

        $ffactory = new FieldFactory();

        $data = [
            'allNDSField'      => $ffactory->buildMap($rule->getRoleType()),
            'meta'             => [ $newCondition->getId() => $meta ],
            'currentCondition' => $newCondition,
            'currentRule'      => [ 'id' => $rule->getId() ]
        ];

        // the list of templates available for rendering
        $templates = [
            self::CONDITION_GENERAL => '@syncCondition/general.twig',
            self::CONDITION_USER    => '@syncCondition/userExists.twig'
        ];

        if (isset($templates[$params['conditionType']])) {
            $this->getResponse()->content->set([
                'template' => $templates[$params['conditionType']],
                'data'     => $data,
                'format'   => 'nochrome'
            ]);
        } else {
            // TODO uh... how do I throw a 400 from here?
        }
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
}