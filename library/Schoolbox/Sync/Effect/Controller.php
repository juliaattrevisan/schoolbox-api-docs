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

class Controller extends Base {
    const EFFECT_SKIP = 1;

    const TYPE_BOOLEAN = 1;
    const TYPE_LOOKUP = 2;
    const TYPE_STRING = 3;

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

        /* Spawn rule
         */
        $rule = Rule\Mapper::getInstance()->findById($params['ruleId']);

        /* TODO: Manufacture and save this properly
         */
        $newEffect = Effect\Mapper::getInstance()->createModel();
        $newEffect->setId(rand(1,1000));

        $data = [
            'allUserField'  => $allUserField,
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

        /* if mappingtype is set to Equals:
         *    if $_GET['userField'] is a boolean, present Boolean select options
         *    else if $_GET['userField'] is a lookup, present Lookup select options
         *    else if $_GET['userField'] is a generic varchar, present input field
         * else
         *    present mapping
         *
         * TODO: Update with actual values
         */
        $mappingType = [
            1 => 'equals',
            2 => 'maps'
        ];

        /* Manufacture a list of Schoolbox User fields to chooze from
         * TODO: Connect up to UserMapper to determine type
         * TODO: This should contain a human-friendly version
         */
        $allFields = [
            'email' => self::TYPE_STRING,
            'altEmail' => self::TYPE_STRING,
            'webmail' => self::TYPE_BOOLEAN,
            'eportfolioEnable' => self::TYPE_BOOLEAN,
            'externalId' => self::TYPE_STRING,
            'firstname' => self::TYPE_STRING,
            'lastname' => self::TYPE_STRING,
            'localEmail' => self::TYPE_BOOLEAN,
            'residentialHouse' => self::TYPE_LOOKUP,
            'schoolHouse' => self::TYPE_LOOKUP,
            'title' => self::TYPE_LOOKUP,
            'useInternalMail' => self::TYPE_BOOLEAN,
            'username' => self::TYPE_STRING,
            'year' => self::TYPE_STRING,
            'enabled' =>  self::TYPE_BOOLEAN,
            'boardingStatus' => self::TYPE_STRING,
        ];

        $template = '';

        /* TODO: Replace with something properly considered
         * These were purely for trying things out
         */
        if ($mappingType[$_GET['mappingType']] == 'equals') {
            if ($allFields[$_GET['userField']] == self::TYPE_BOOLEAN) {
                $template = '@syncEffect/boolean.twig';
            }
            else if ($allFields[$_GET['userField']] == self::TYPE_LOOKUP) {
                $template = '@syncEffect/lookup.twig';
            }
            else if ($allFields[$_GET['userField']] == self::TYPE_STRING) {
                $template = '@syncEffect/varchar.twig';
            }
        }
        else {
            $template = '@syncEffect/map.twig';
        }

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