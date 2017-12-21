<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Alaress\Web\Controller\Base;
use Schoolbox\Error;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

class Controller extends Base
{

    /**
     * Const for when accreditation succeeds
     */
    private const ACCREDIT_SUCCESS = 1;

    /**
     * Const for when accreditation fails
     */
    private const ACCREDIT_FAIL = 0;

    /**
     * @var Mapper
     */
    private $pdMapper;

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     * @param Mapper       $pdMapper
     * @param Error\Mapper $errorMapper
     */
    public function __construct(Mapper $pdMapper, Error\Mapper $errorMapper)
    {
        $this->pdMapper = $pdMapper;
        $this->errorMapper = $errorMapper;
    }

    /**
     * Save a Professional Development Number + Registration State combination
     *
     * @param User $user
     * @throws MapperException
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function postUpdate(User $user)
    {
        $number = $this->getRequest()->post['pdNumber'];
        $state = $this->getRequest()->post['pdState'];

        $result = (new Repository($this->pdMapper))->save($user, $number, $state);

        /* Redirect upon successful save
         */
        if ($result) {
            $this->getResponse()->redirect->to("/search/user/{$user->getId()}");
            return;
        }

        /* Otherwise...
         */
        throw $this->errorMapper->generateException(Error\Mapper::ERROR_INVALID_PD_DETAILS);
    }


    /**
     * Update someone's accreditation
     *
     * @param int $userId
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postAccredit(int $userId)
    {
        try {
            /** @var Model $pd */
            $pd = $this->pdMapper->findOneByColumn('user_id', $userId);

            $pd->setAccreditedAt(\Alaress_Date_Format::now());

            $this->pdMapper->save($pd);

            $status = self::ACCREDIT_SUCCESS;
        }
        catch (MapperException $e) {
            $status = self::ACCREDIT_FAIL;
        }

        $this->getResponse()->content->set($this->buildJsonOutput([
            'userId' => $userId,
            'status' => $status
        ]));
    }
}
