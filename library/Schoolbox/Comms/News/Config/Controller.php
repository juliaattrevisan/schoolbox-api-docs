<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Config;


use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Config\Policy\GlobalModeration;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;


class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private $userMapper;

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
     * @param UserMapper       $userMapper
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper, UserAccessMapper $userAccessMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
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
    public function getConfig()
    {
        $commsOfficers = $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_COMMS_OFFICER)->getUnique('id');

        /* TODO: Get current policy settings from db
         */
        $policies = (new Collection())
            ->add(new GlobalModeration());

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsConfig/index.twig', [
            'commsOfficers' => $commsOfficers,
            'policies' => $policies
        ])->setTitle('Comms Configuration'));
    }


    /**
     * Process the form vars
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postConfig()
    {
        $policies = (new Collection())
            ->add(new GlobalModeration());

        foreach ($this->getRequest()->post as $identifier => $value) {
            $existingPolicy = $policies->getBy('identifier', $identifier);

            /* TODO: Persist values to db
             */
            if ((int)$value) {
                $existingPolicy->activate();
            }
            else {
                $existingPolicy->deactivate();
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsConfig/submit.twig', [
            'policies' => $policies
        ])->setTitle('Update Comms settings'));
    }
}