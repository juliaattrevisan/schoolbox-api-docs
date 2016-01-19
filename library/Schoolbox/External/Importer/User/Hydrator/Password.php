<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

/**
 * Sets the user's password, if it exists in the row and it isn't empty.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Password extends Field
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Constructor
     *
     * @param string     $fieldname
     * @param string     $setMethod
     * @param UserMapper $userMapper
     */
    public function __construct($fieldname, $setMethod, UserMapper $userMapper)
    {
        parent::__construct($fieldname, $setMethod);
        $this->userMapper = $userMapper;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        if (isset($row[$this->fieldname]) && strlen($row[$this->fieldname]) > 0) {
            $password = $this->userMapper->hashPassword($row[$this->fieldname]);
            return call_user_func([$user, $this->setMethod], $password);
        }
        return $user;
    }
}
