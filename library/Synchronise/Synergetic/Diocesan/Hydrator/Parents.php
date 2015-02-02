<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use External_UserInterface;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\User;
use Synchronise_Base_Hydrator_Spouse;
use Synchronise_Base_IHydrator;

class Parents implements Synchronise_Base_IHydrator
{
    const ROLE_PARENT = 16;

    /** @var Synchronise_Base_IHydrator */
    protected $base;

    /** @var External_UserInterface */
    protected $euHandler;

    /** @var Mapper */
    protected $userMapper;


    /**
     * @param Synchronise_Base_IHydrator $base
     * @param External_UserInterface     $eu
     * @param Mapper                     $userMapper
     */
    function __construct(Synchronise_Base_IHydrator $base, External_UserInterface $eu, Mapper $userMapper)
    {
        $this->base = $base;
        $this->euHandler = $eu;
        $this->userMapper = $userMapper;
    }


    /**
     * @param array $externalUser
     * @param array $newUser
     * @return array
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser = $this->base->hydrate($externalUser, $newUser);
        $newUser['campus'] = array();
        $newUser['group_id'] = self::ROLE_PARENT;

        $lookup   = $this->base instanceof Synchronise_Base_Hydrator_Spouse ? 'StudentContactSpouseID' : 'StudentContactSpouseID';
        $lookupId = $externalUser[$lookup];

        // BECAUSE THERE MIGHT NOT BE DATA! WHY WOULD THERE NOT BE DATA!?!?!
        if (!$lookupId) {
            return $newUser;
        }

        // THIS MIGHT BE FALSE! THE CAKE MIGHT BE A LIE!
        $enrolments = $this->euHandler->getEnrolledStudents($externalUser[$lookup]);

        // because loose typing, because PHP, because reasons.
        if (!is_array($enrolments) || !count($enrolments)) {
            // parent with no children........ ??
            return $newUser;
        }

        // extract the values from array, because every element is an array with ONE IRRELEVANTLY-NAMED ELEMENT in it...
        $childExtIds = array_map(
            function ($v) {
                return $v['RelatedID'];
            }, $enrolments
        );


        /** @var Collection|User[] $children */
        $childIds = $this->userMapper->findByColumn('synergy_id', $childExtIds, array(
            'order' => array('year' => 'DESC')
        ))->getColumnValues('id');

        // BECAUSE THERE MIGHT NOT BE CHILDREN!!!!! AGAIN!!!!
        if (!count($childIds)) {
            return $newUser;
        }

        $children = new Collection();

        // this is ridiculous. truly.
        foreach ($childIds as $id) {
            try {
                $children->add($this->userMapper->findById($id));
            } catch (Mapper\Exception $e) {
                // noop
            }
        }

        // BECAUSE THERE MIGHT NOT BE CHILDREN!!!! AGAIN AGAIN!!!!!
        if (!$children->count()) {
            return $newUser;
        }

        /** @var User $eldest */
        $eldest = $children->first();

        if (!$eldest->getDefaultCampus() instanceof Campus) {
            // *sigh*
            if ($eldest->getCampusCollection()->count()) {
                $eldest->setDefaultCampus($eldest->getCampusCollection()->first());
            } else {
                return $newUser;
            }
        }

        $newUser['defaultCampus'] = $eldest->getDefaultCampus()->getId();

        $newUser['campus'] = array_unique($children->map(function ($user) {
            /** @var User $user */
            return $user->getDefaultCampus()->getId();
        })->getAll());

        return $newUser;
    }

} 