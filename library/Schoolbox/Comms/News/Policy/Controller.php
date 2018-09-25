<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Policy;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\TopicMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;


class Controller extends Base
{

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var Mapper
     */
    private $policyMapper;

    /**
     * @var TopicMapper
     */
    private $topicMapper;

    /**
     * @param UserAccessMapper $userAccessMapper
     * @param Mapper           $policyMapper
     * @param TopicMapper      $topicMapper
     */
    public function __construct(UserAccessMapper $userAccessMapper, Mapper $policyMapper, TopicMapper $topicMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        $this->policyMapper = $policyMapper;
        $this->topicMapper = $topicMapper;
    }


    /**
     * Provide form
     *
     * @api GET /comms/news/policy
     *
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function getPolicies()
    {
        $commsOfficers = $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_COMMS_OFFICER)->getUnique('id')->indexBy('id');
        $policies = $this->policyMapper->findAll();

        /* Superusers and Comms Officers can change policy settings
         */
        $isConfigurable = $this->getUser()->isSuperuser() || $commsOfficers->getOffset($this->getUser()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsPolicy/index.twig', [
            'commsOfficers' => $commsOfficers,
            'isConfigurable' => $isConfigurable,
            'policies' => $policies,
            'topics' => $this->topicMapper->findAll(['order' => ['sequence' => 'asc']]),
            'pageCurrent' => 'news'
        ])->setTitle('Comms Policies'));
    }


    /**
     * Save policy settings
     *
     * @api POST /comms/news/policy
     *
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postPolicies()
    {
        $policies = $this->policyMapper->findAll();

        foreach ($policies as $policy) {
            if (isset($this->getRequest()->post['policy'][$policy->getIdentifier()])) {
                $policy->setValue($this->getRequest()->post['policy'][$policy->getIdentifier()]);
            }
            else {
                $policy->setValue('');
            }

            $this->policyMapper->save($policy);
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('commsNewsPolicy.getPolicies'));
    }
}
