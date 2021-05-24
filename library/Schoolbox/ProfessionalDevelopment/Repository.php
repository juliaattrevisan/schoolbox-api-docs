<?php

/**
 * Repository
 *
 * Essentially a Facade for saving a Professional Development instance, abstracting away the mechanism by which a PD
 * instance is spawned and populated before saving.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;

class Repository
{
    /**
     * @var Mapper
     */
    private $pdMapper;

    /**
     * Repository constructor.
     * @param Mapper $pdMapper
     */
    public function __construct(Mapper $pdMapper)
    {
        $this->pdMapper = $pdMapper;
    }

    /**
     * Save professional development data
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
    public function save(User $user, ?string $pdNumber, ?string $pdState)
    {
        /** @var Model $pd */
        try {
            $pd = $this->pdMapper->findOneByColumn('user_id', $user->getId());
        } catch (MapperException $e) {
            $pd = $this->pdMapper->createModel();
            $pd->setUser($user);
        }

        /* Intentionally filter invalid chars
         */
        $pd->setNumber(preg_replace('/[^a-zA-Z0-9]/', '', $pdNumber));
        $pd->setRegistrationState($pdState);

        $validation = new ValidProfessionalDevelopment($this->pdMapper->getStates());

        /* Only save if valid data exists; otherwise noop
         * Catch all the errors that Mapper->save can throw and bin them for now
         */
        if ($validation->isSatisfiedBy($pd)) {
            try {
                Mapper::getInstance()->save($pd);
            } catch (\PDOException $e) {
                return false;
            } catch (\UnexpectedValueException $e) {
                return false;
            }

            return true;
        }

        return false;
    }
}
