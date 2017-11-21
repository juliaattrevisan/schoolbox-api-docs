<?php
/**
 * Repository
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;

class Repository
{

    /**
     * Save profesional development data
     * Only save if valid data exists; otherwise noop
     * (PD data is optional to provide)
     *
     * @param User   $user
     * @param string $pdNumber
     * @param string $pdState
     * @return bool
     * @throws MapperException
     * @throws \PDOException
     */
    public function save(User $user, string $pdNumber, string $pdState)
    {
        /** @var Model $pd */
        try {
            $pd = Mapper::getInstance()->findOneByColumn('user_id', $user->getId());
        }
        catch (MapperException $e) {
            $pd = Mapper::getInstance()->createModel();
            $pd->setUser($user);
        }

        /* Intentionally filter invalid chars
         */
        $pd->setNumber(preg_replace('/[^a-zA-Z0-9]/', '', $pdNumber));
        $pd->setRegistrationState($pdState);

        $validation = new ValidProfessionalDevelopment(Mapper::getInstance()->getStates());

        /* Only save if valid data exists; otherwise noop
         */
        if ($validation->isSatisfiedBy($pd)) {
            Mapper::getInstance()->save($pd);

            return true;
        }

        return false;
    }
}