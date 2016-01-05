<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\User\Identifier;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

/**
 * Identifies a user based on the username in the row.
 *
 * @package Schoolbox\External\Importer\User\Identifier
 */
class Username implements IdentifierInterface
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     */
    public function __construct(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * @inheritdoc
     */
    public function accept($row)
    {
        return strlen($row['Username']) > 0;
    }

    /**
     * @inheritdoc
     */
    public function retrieve($row)
    {
        try {
            return $this->userMapper->findOneByColumn('username', $row['Username']);
        } catch (MapperException $e) {
            return $this->userMapper->createModel();
        }
    }
}
