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
use PDepend\Util\Type;
use Schoolbox\Sync\Grammar\FieldFactory;
use Schoolbox\Sync\Rule;
use Schoolbox\Sync\Effect;
use Alaress\ObjectReader;
use Schoolbox\Sync\Effect\Fragment\Factory as FragmentFactory;
use \Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;

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
        'status' => 'Effect not yet saved'
    ];

    /**
     * Forge a data structure to feed into twig template,
     * so that new Effects can be injected directly into
     * the ui after being received via ajax
     *
     * @param $params
     */
    public function add($params) {

        /* Spawn Rule
         */
        $rule = Rule\Mapper::getInstance()->findById($params['ruleId']);

        /* TODO: Remove Hardcoding
         * Assume Literal Effect Type to start with
         */
        $default = [
            'effectType' => null,
            'userProperty' => null,
            'arguments' => [false]
        ];

        /* Don't make a stub, due to (rule_id,user_property) uniqueness
         * which prevents the same field from being modified multiple times
         * in the same rule. As a result, the "primary key" for this
         * new Effect instance will simply be "new" and the js will have to
         * swap that out for something else, such as "new-1", "new-2" etc.
         * to maintain uniqueuness in that land.
         */
        $effectTypeMapper = new EffectTypeMapper();
        $effectType = $effectTypeMapper->createModel();

        $newEffect = Effect\Mapper::getInstance()->createModel();
        $newEffect
            ->setId('new')
            ->setRule($rule)
            ->setUserProperty($default['userProperty'])
            ->setArguments($default['arguments'])
            ->setEffectType($effectType);

        /* Decide which Effect Fragment to use
         */
        $fragmentFactory = new FragmentFactory();

        /* All fragments and subfragments use empty by default, thus
         * forcing the user to choose a userfield/mappingType combo
         */
        $fragment = $fragmentFactory->produceEmpty();

        $data = [
            'allEffectStructures' => [
                'fragments' => [
                    $newEffect->getId() => [
                        'fragment' => $fragment,
                    ]
                ],
                'allUserField' => $fragmentFactory->getAllFieldsKeyValue(),
            ],
            'meta' => [
                'effect' => [
                    $newEffect->getId() => $this->getMeta()
                ]
            ],
            'currentEffect' => $newEffect,
            'currentRule' => [
                'id' => $rule->getId()
            ]
        ];

        $this->getResponse()->content->set([
            'template' => '@upsEffect/container.twig',
            'data'     => $data,
            'format'   => 'nochrome'
        ]);
    }

    /**
     * Add a special skip effect
     *
     * @param $params
     */
    public function addSkip($params) {

        /* Spawn Rule
         */
        $rule = Rule\Mapper::getInstance()->findById($params['ruleId']);

        /* TODO: Remove Hardcoding
         * Assume Literal Effect Type to start with
         */
        $default = [
            'effectType' => null,
            'userProperty' => null,
            'arguments' => []
        ];

        /* Don't make a stub, due to (rule_id,user_property) uniqueness
         * which prevents the same field from being modified multiple times
         * in the same rule. As a result, the "primary key" for this
         * new Effect instance will simply be "new" and the js will have to
         * swap that out for something else, such as "new-1", "new-2" etc.
         * to maintain uniqueuness in that land.
         */
        $effectTypeMapper = new EffectTypeMapper();
        $effectType = $effectTypeMapper->createModel();

        $newEffect = Effect\Mapper::getInstance()->createModel();
        $newEffect
            ->setId('new')
            ->setRule($rule)
            ->setUserProperty($default['userProperty'])
            ->setArguments($default['arguments'])
            ->setEffectType($effectType);

        $data = [
            'meta' => [
                'effect' => [
                    $newEffect->getId() => $this->getMeta()
                ]
            ],
            'currentEffect' => $newEffect,
        ];

        $this->getResponse()->content->set([
            'template' => '@upsEffect/container-skip.twig',
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
        $queryData = $this->getRequest()->query;

        $fragmentFactory = new FragmentFactory();
        $fragment = $fragmentFactory->produce($queryData['mappingType'], $queryData['userField']);
        $subfragment = $fragmentFactory->produceType($queryData['userField']);

        /* Contains Lookup data
         */
        $data = [
            'allEffectStructures' => [
                'fragments' => [
                    $queryData['effectId'] => [
                        'fragment' => $fragment,
                        'subfragments' => [
                            'fragment' => $subfragment,
                        ]
                    ]
                ],
            ],

            /* If this Effect hasn't been saved it, it won't have a ruleId
             * nor an effectId, so recycle it from frontend, as fragments
             * expect both values
             */
            'currentEffect' => [
                'id' => $queryData['effectId'],
                'rule' => [
                    'id' => $queryData['ruleId'],
                ],
            ]
        ];

        $this->getResponse()->content->set([
            'template' => $fragment->getFilename(),
            'data' => $data,
            'format' => 'nochrome'
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

    /**
     * Spawn a new mapped row fragment
     */
    public function newMappedRow() {
        $queryData = $this->getRequest()->query;

        $effect = [
            'id' => $queryData['effectId'],
            'rule' => [
                'id' => $queryData['ruleId'],
            ]
        ];

        $fragmentFactory = new FragmentFactory();
        $subfragment = $fragmentFactory->produceType($queryData['userProperty']);

        $data = [
            'currentEffect' => $effect,
            'meta' => [
                'effect' => [
                    $queryData['effectId'] => $this->getMeta()
                ]
            ],
            'allEffectStructures' => [
                'fragments' => [
                    $queryData['effectId'] => [
                        'subfragments' => [
                            'fragment' => $subfragment,
                        ]
                    ]
                ]
            ],

            /* Special case for async injecting pre-forged twig templates -_-
             * Normally this is derived in twig land but in this case, a fully
             * formed html file is rammed in and thus need to inject this manually
             */
            'dynamicInputName' => "rule[{$queryData['ruleId']}][effect][{$queryData['effectId']}][to][]",
        ];

        $this->getResponse()->content->set([
            'template' => '@upsEffect/map-row.twig',
            'data'     => $data,
            'format'   => 'nochrome'
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