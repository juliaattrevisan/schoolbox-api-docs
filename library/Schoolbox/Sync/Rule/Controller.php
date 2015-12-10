<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rule;

use Alaress\Web\Controller\Base;
use \Schoolbox\Sync\Navigator\Navigator;
use \Schoolbox\Sync\Navigator\Node;

class Controller extends Base {

    /**
     * Form
     *
     * @param $params
     */
    public function add($params) {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2/home', false),
            new Node('User Provisioning System', '/adminv2/ups', false),
            new Node(ucwords($params['rulesetId']), "/adminv2/ups/{$params['rulesetId']}", false),
            new Node('Add Rule', '#', true)
        );

        $this->getResponse()->content->set([
            'template' => '@upsRule/add.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'rulesetId' => $params['rulesetId'],

                /* TODO: Empty Rule instance
                 */
                'rule' => []
            ]
        ]);
    }

    /**
     * Perform
     *
     * @param $params
     */
    public function create($params)
    {
        $mapper = Mapper::getInstance();

        $postData = $this->getRequest()->post;

        $roleType = $postData->get('rulesetId');

        /* Rules are always enabled, for now
         */
        $rule = $mapper->createModel()
                       ->setRoleType($roleType)
                       ->setName($postData->get('name'))
                       ->setDescription($postData->get('description'))
                       ->setEnabled(true)
                       ->setSequence($mapper->findNextAvailableSequence($roleType));

        $mapper->create($rule);

        $this->getResponse()->redirect->to("/adminv2/ups/$roleType#rule-{$rule->getId()}");
    }

    /**
     * Form
     *
     * @param $params
     */
    public function edit($params)
    {
        $rule = Mapper::getInstance()->findById($params['ruleId']);

        $rulesetId = $rule->getRoleType();

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2/home', false),
            new Node('User Provisioning System', '/adminv2/ups', false),
            new Node(ucwords($rulesetId), "/adminv2/ups/{$rulesetId}", true)
        );

        $this->getResponse()->content->set([
            'template' => '@upsRule/add.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'rulesetId' => $rulesetId,
                'rule' => $rule
            ]
        ]);
    }

    /**
     * Perform
     *
     * @param $params
     */
    public function update($params)
    {
        $postData = $this->getRequest()->post;

        $rule = Mapper::getInstance()
            ->findById($postData->get('ruleId'))
            ->setRoleType($postData->get('rulesetId'))
            ->setName($postData->get('name'))
            ->setDescription($postData->get('description'));

        Mapper::getInstance()->save($rule);

        $this->getResponse()->redirect->to("/adminv2/ups/{$postData->get('rulesetId')}#rule-{$rule->getId()}");
    }

    /**
     * Test Method
     *
     * @param $params
     */
    public function delete($params)
    {
        $ruleId = $this->getRequest()->post->get('ruleId');
        $deleteCount = Mapper::getInstance()->deleteByColumn('id', $ruleId);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $deleteCount > 0
        ]);

    }
}