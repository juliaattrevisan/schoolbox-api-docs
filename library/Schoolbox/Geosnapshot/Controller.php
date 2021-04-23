<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use Schoolbox\UserMapper;


/**
 * Class Controller
 *
 * @package Schoolbox\Geosnapshot
 */
class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;


    /**
     * Controller constructor.
     *
     * @param UserMapper  $userMapper
     */
    public function __construct(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }


    /**
     * With a provided url, make a request to Geosnapshot
     *
     * @param User $user
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getTransmit(User $user): void
    {
        echo 'made it';
        die();
    }
}
