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
     * Controller constructor.
     *
     * @param ErrorMapper      $errorMapper
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserAccessMapper $userAccessMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userAccessMapper = $userAccessMapper;
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
        $policies = Mapper::getInstance()->findAll();

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsPolicy/index.twig', [
            'commsOfficers' => $commsOfficers,
            'policies' => $policies
        ])->setTitle('Comms Policies'));
    }


    /**
     * Process the form vars
     *
     * @throws MapperException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postPolicies()
    {
        $policies = Mapper::getInstance()->findAll();

        foreach ($policies as $policy) {
            if (isset($this->getRequest()->post['policy'][$policy->getIdentifier()])) {
                $policy->setValue($this->getRequest()->post['policy'][$policy->getIdentifier()]);
            }
            else {
                $policy->setValue('');
            }

            Mapper::getInstance()->save($policy);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsPolicy/submit.twig', [
            'policies' => $policies
        ])->setTitle('Update Comms Policies'));
    }
}