<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Identifier;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

use function trim;

/**
 * Identifies a user based on the Schoolbox user ID in the row.
 *
 * @package Schoolbox\External\Importer\User\Identifier
 */
class UserId implements IdentifierInterface
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
        return trim($row['Schoolbox User ID'] ?? '') !== '';
    }

    /**
     * @inheritdoc
     */
    public function retrieve($row)
    {
        $userId = $row['Schoolbox User ID'];
        try {
            return $this->userMapper->findOneByColumn($this->userMapper->getIdentifierColumn(), $userId, ['cache' => false]);
        } catch (MapperException $e) {
            throw new \RuntimeException(sprintf('User with ID "%s" not found', $userId));
        }
    }
}
