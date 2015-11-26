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
use Alaress\ObjectReader;
use Schoolbox\Sync\Effect\AllUserField;

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

        /* Manufacture a list of Schoolbox User fields to chooze from
         * TODO: This should contain a human-friendly version
         */
        $reader = new ObjectReader();
        $userMapper = new \Schoolbox\UserMapper();
        $user = $userMapper->createModel();
        $paths = $reader->paths($user);
        $allUserField = array_combine($paths, $paths);

        /* Spawn Rule
         */
        $rule = Rule\Mapper::getInstance()->findById($params['ruleId']);

        /* TODO: Remove Hardcoding
         * Assume Literal Effect Type to start with
         */
        $default = [
            'effectType' => AllUserField::EFFECT_TYPE_LITERAL,
            'userProperty' => 'lastname',
            'arguments' => [null]
        ];

        /* Add stub Effect for this Rule
         */
        $newEffect = Effect\Mapper::getInstance()->createModel();
        $newEffect
            ->setRule($rule)
            ->setUserProperty($default['userProperty'])
            ->setArguments($default['arguments'])
            ->setEffectType(Effect\Type\Mapper::getInstance()->findById($default['effectType']));

        Effect\Mapper::getInstance()->create($newEffect);

        /* Decide which Effect Fragment to use
         */
        $allUserField = new AllUserField();

        $data = [
            'allEffectFragments' => [
                $newEffect->getId() => $allUserField->getEffectFragmentTemplate($default['effectType'], $default['userProperty'])
            ],
            'allUserField'  => $allUserField->getAllFields(),
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

    /**
     * Decide whether to return
     * a boolean select,
     * a lookup select,
     * or a freeform text input
     */
    public function change() {
        $allUserField = new AllUserField();
        $template = $allUserField->getEffectFragmentTemplate($_GET['mappingType'], $_GET['userField']);

        $this->getResponse()->content->set([
            'template' => $template,
            'data'     => [],
            'format'   => 'nochrome'
        ]);
    }

    /**
     * Delete a Effect asynchronously
     *
     * @return bool
     */
    public function delete()
    {
        $effectId = $this->getRequest()->post->get('effectId');
        $deleteCount = Effect\Mapper::getInstance()->deleteByColumn('id', $effectId);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $deleteCount > 0
        ]);
    }
}