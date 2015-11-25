<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect;

use Alaress\Web\Controller\Base;
use Schoolbox\Sync\Grammar\FieldFactory;
use Schoolbox\Sync\Rule;
use Schoolbox\Sync\Effect;

class Controller extends Base {
    const EFFECT_SKIP = 1;

    /**
     * Forge a data structure to feed into twig template,
     * so that new Effects can be injected directly into
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
            'status' => 'Effect not yet saved'
        ];

        $rule = Rule\Mapper::getInstance()->findById($params['ruleId']);

        $newEffect = Effect\Mapper::getInstance()->createModel();

        /* TODO: Manufacture and save this properly
         */
        $newEffect->setId(1);

        $data = [
            'allUserField'  => [
                'username'  => 'Username',
                'firstname' => 'First Name',
                'surname'   => 'Last Name'
            ],
            'meta'          => [ $newEffect->getId() => $meta ],
            'currentEffect' => $newEffect,
            'currentRule'   => [ 'id' => $rule->getId() ]
        ];

        $this->getResponse()->content->set([
            'template' => '@syncEffect/container.twig',
            'data'     => $data,
            'format'   => 'nochrome'
        ]);
    }
}