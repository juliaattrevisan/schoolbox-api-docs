<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Exception;
use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\User;
use Synchronise_Base_Hydrator_Parent;

class Parents extends Synchronise_Base_Hydrator_Parent
{
    const ROLE_PARENT = 16;


    /** @var External_UserInterface */
    protected $euHandler;

    /** @var Mapper */
    protected $userMapper;

    /**
     * @param External_UserInterface $eu
     * @param Mapper $userMapper
     */
    function __construct(External_UserInterface $eu, Mapper $userMapper)
    {
        $this->euHandler = $eu;
        $this->userMapper = $userMapper;
    }


    /**
     * @param array $externalUser
     * @param array $newUser
     * @return array
     * @throws Exception
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser = parent::hydrate($externalUser, $newUser);

        $newUser['group_id'] = self::ROLE_PARENT;

        // provide access to all campuses of children
        // make eldest-child campus the default
        $childExtIds = $this->euHandler->getEnrolledStudents($externalUser['StudentContactID']);

        /** @var Collection|User[] $children */
        $children = $this->userMapper->findByColumn('synergy_id', $childExtIds, array(
            'order' => array('year' => 'DESC')
        ));

        /** @var User $eldest */
        $eldest = $children->first();

        $newUser['defaultCampus'] = $eldest->getDefaultCampus()->getId();

        $newUser['campus'] = array_unique($children->map(function ($user) {
            /** @var User $user */
            return $user->getDefaultCampus()->getId();
        })->getAll());

        return $newUser;
    }

} 