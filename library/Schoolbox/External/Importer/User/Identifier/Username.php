<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Identifier;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

use function trim;

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
        return trim($row['Username'] ?? '') !== '';
    }

    /**
     * @inheritdoc
     */
    public function retrieve($row)
    {
        try {
            return $this->userMapper->findOneByColumn('username', $row['Username'], ['cache' => false]);
        } catch (MapperException $e) {
            return $this->userMapper->createModel();
        }
    }
}
