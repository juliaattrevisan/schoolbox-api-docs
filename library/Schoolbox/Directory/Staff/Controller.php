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
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper    $errorMapper
     * @param UserMapper     $userMapper
     * @param \External_User $externalUser
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private UserMapper $userMapper,
        private \External_User $externalUser
    ) {
    }


    /**
     * Provide list of staff members in this directory
     */
    public function getList(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@staffDirectory/index.twig', [
        ])->setTitle('Staff Directory'));
    }


    /**
     * Return a json payload of all the staff members
     *
     * @return void
     * @throws \Alaress\Database\Exception
     */
    public function getPayload(): void
    {
        $this->response->content->set($this->buildJsonOutput((new Payload($this->userMapper, $this->externalUser))->generate()));
    }
}
