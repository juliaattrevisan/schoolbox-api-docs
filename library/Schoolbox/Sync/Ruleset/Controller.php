<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Ruleset;

use Alaress\Web\Controller\Base;
use \Schoolbox\Sync\Navigator\Navigator;
use \Schoolbox\Sync\Navigator\Node;
use \Schoolbox\Sync\Condition;
use \Schoolbox\Sync\Effect;
use \Schoolbox\Sync\Hydrator;
use \Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;
use \Schoolbox;
use Schoolbox\Sync\Effect\Fragment\Factory as FragmentFactory;

class Controller extends Base {

    /**
     * Render intro
     */
    public function intro() {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2/home', false),
            new Node('User Provisioning System', '/adminv2/sync', true)
        );

        /* Navigation
         */
        $allNavigation = new Navigator(
            new Node('Student', '/adminv2/sync/student', false),
            new Node('Parent', '/adminv2/sync/parent', false),
            new Node('Staff', '/adminv2/sync/staff', false)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/intro.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'allNavigation' => $allNavigation->getAllNodes(),
            ]
        ]);
    }

    /**
     * Ruleset view
     *
     * @param $params
     */
    public function read($params) {

        /* Produce Twig template data based on Role
         */
        $factory = new Factory($params['rulesetId']);

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/ruleset.twig',
            'data' => $factory->produce()
        ]);
    }

    /**
     * Update each Rule in the Ruleset
     */
    public function update()
    {
        $postData = $this->getRequest()->post;

        $roleType = $postData['rulesetId'];

        $this->updateConfig($roleType, $postData['config'][$roleType]);
        foreach ($postData['rule'] as $ruleId => $ruleData) {
            $this->updateConditions($ruleId, $ruleData['condition']);
            $this->updateEffects($ruleId, $ruleData['effect']);
        }
        $this->updateCustomRules($roleType, $postData['customRule']);
        $this->getResponse()->redirect->to("/adminv2/sync/$roleType");
    }

    /**
     * @param $roleType
     * @param $config
     */
    protected function updateConfig($roleType, $config)
    {
        // TODO DI all of this
        $mapper = Config\Mapper::getInstance();
        $model  = $mapper->findById($roleType);
        $model->setEnabled($config['enabled']);
        $mapper->save($model);
    }

    /**
     * @param int $ruleId
     * @param array $conditions
     */
    protected function updateConditions($ruleId, $conditions)
    {
        // TODO DI
        $mapper = Condition\Mapper::getInstance();

        if (is_array($conditions)) {
            foreach ($conditions as $conditionId => $conditionData) {

                $conditionType = $conditionData['type'];
                // NOT WORKING
                $conditionArgs = (array)$conditionData['arguments'];

                // ensure this is in the right order
                ksort($conditionArgs);

                // this is pretty hacky and not overly extensible...
                if ($conditionType == 1 && count($conditionArgs) == 3) {
                    if (strlen($conditionArgs[1])) {
                        $conditionArgs[1] = preg_split("`[\r\n]+`", $conditionArgs[1]);
                    }
                    $conditionArgs[2] = (bool)$conditionArgs[2];

                } else {
                    $conditionArgs[0] = (bool)$conditionArgs[0];
                }

                $condition = [
                    'id'                => $conditionId,
                    'rule_id'           => $ruleId,
                    'condition_type_id' => $conditionType,
                    'arguments'         => json_encode($conditionArgs)
                ];

                try {
                    $mapper->save($mapper->hydrate($condition));
                }
                catch (\PDOException $e) {
                }
            }
        }

        // housekeeping - make sure there aren't any remnant invalid conditions flying around
        $mapper->deleteByColumnList([
            'rule_id'   => $ruleId,
            'arguments' => json_encode(null)
        ]);
    }

    /**
     * @param int $ruleId
     * @param array $effects
     */
    protected function updateEffects($ruleId, $effects)
    {
        // TODO DI
        $mapper = Effect\Mapper::getInstance();

        $effectTypeMapper = new EffectTypeMapper();

        if (is_array($effects)) {
            foreach ($effects as $effectId => $effectData) {
                $effectArgs = (array)$effectData['arguments'];

                // ensure this is in the right order
                ksort($effectArgs);

                /* Figure out what type it was, and cast accordingly
                 */
                $fragmentFactory = new FragmentFactory();

                /* Forge key/value for Mapped Key/Value Pairs
                 */
                if ($effectTypeMapper->isMap($effectData['type'])) {

                    /* Keep arg[0], that token field
                     */
                    $arg = $effectData['arguments'];

                    /* Forge this into key/value pairs and ram that
                     * into $args as well
                     */
                    $pairs = array_combine($effectData['from'], $effectData['to']);

                    /* array_filter filters out values that equate to false
                     * such as zero (which is valid data...). Use unset pears
                     * approach instead
                     *
                     * http://stackoverflow.com/questions/243132/how-do-you-remove-a-value-that-has-an-empty-key-from-an-associative-array-in-php
                     */
                    unset($pairs[null]);

                    $arg[] = $pairs;
                }
                /* Force cast of boolean arg so it doesn't save in some
                 * other data format.
                 *
                 * Sequential Dependency:
                 * This has to take place after isMap() check because the
                 * Mapped Key/Value Pairs can utilise a boolean field
                 */
                else if ($fragmentFactory->isBoolean($effectData['userProperty'])) {
                    $arg = [(bool)$effectArgs[0]];
                }
                /* Otherwise just save it as given
                 */
                else {
                    $arg = $effectArgs;
                }

                $effect = [
                    'id'             => $effectId,
                    'rule_id'        => $ruleId,
                    'effect_type_id' => $effectData['type'],
                    'user_property'  => $effectData['userProperty'],
                    'arguments'      => json_encode($arg)
                ];

                try {
                    $mapper->save($mapper->hydrate($effect));
                }
                catch (\PDOException $e) {
                }
            }
        }

        // housekeeping - make sure there aren't any remnant invalid effects flying around
        $mapper->deleteByColumnList([
            'rule_id'   => $ruleId,
            'arguments' => json_encode(null)
        ]);
    }

    /**
     * Saves custom rules for this role type.
     *
     * @param string $roleType
     * @param array  $customRules Custom rule options
     */
    protected function updateCustomRules($roleType, $customRules)
    {
        if (!is_array($customRules)) {
            return;
        }

        // TODO DI some more
        $mapper = Hydrator\Custom\Mapper::getInstance();
        /** @var Hydrator\Custom\Model[] $rules */
        $rules  = $mapper->findByColumnList([
            'role_type' => $roleType,
            'id'        => array_keys($customRules),
        ]);
        foreach ($rules as $rule) {
            $ruleParams = $customRules[$rule->getId()];
            $rule->setEnabled($ruleParams['enabled']);
        }
        $mapper->save($rules);
    }
}
