<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * @param User $user
     * @throws MapperException
     * @throws \PDOException
     */
    public function postUpdate(User $user)
    {
        /** @var Model $pd */
        try {
            $pd = Mapper::getInstance()->findOneByColumn('user_id', $user->getId());
        }
        catch (MapperException $e) {
            $pd = Mapper::getInstance()->createModel();
            $pd->setUser($user);
        }

        $pd->setNumber(preg_replace('/[^a-zA-Z0-9]/', '', $this->getRequest()->post['pdNumber']));
        $pd->setRegistrationState($this->getRequest()->post['pdState']);

        $validation = new ValidProfessionalDevelopment(Mapper::getInstance()->getStates());

        if ($validation->isSatisfiedBy($pd)) {
            Mapper::getInstance()->save($pd);
        }

        $this->getResponse()->redirect->to("/search/user/{$user->getId()}");
    }
}
