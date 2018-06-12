<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Policy;


use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;


class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var Mapper
     */
    private $policyMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper      $errorMapper
     * @param UserAccessMapper $userAccessMapper
     * @param Mapper           $policyMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserAccessMapper $userAccessMapper, Mapper $policyMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->policyMapper = $policyMapper;
    }


    /**
     * Provide form
     *
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function getPolicies()
    {
        $commsOfficers = $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_COMMS_OFFICER)->getUnique('id');
        $policies = $this->policyMapper->findAll();

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsPolicy/index.twig', [
            'commsOfficers' => $commsOfficers,
            'policies' => $policies
        ])->setTitle('Comms Policies'));
    }


    /**
     * Save policy settings
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