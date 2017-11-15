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
        try {
            $pd = Mapper::getInstance()->findOneByColumn('user_id', $user->getId());
        }
        catch (MapperException $e) {
            $pd = Mapper::getInstance()->createModel();
            $pd->setUser($user);
        }

        $pd->setNumber($this->getRequest()->post['pdNumber']);
        $pd->setRegistrationState($this->getRequest()->post['pdState']);

        Mapper::getInstance()->save($pd);

        $this->getResponse()->redirect->to("/search/user/{$user->getId()}");
    }
}
