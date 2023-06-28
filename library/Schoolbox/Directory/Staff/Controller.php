<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Directory\Staff;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(private ErrorMapper $errorMapper, private UserMapper $userMapper)
    {
    }


    /**
     * Provide form
     */
    public function getList(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@staffDirectory/index.twig', [
        ])->setTitle('Staff Directory'));
    }
}
