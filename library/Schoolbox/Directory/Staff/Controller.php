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
            'payload' => $this->generatePayload()
        ])->setTitle('Staff Directory'));
    }

    /**
     * Generate a set of staff members but augment with additional info
     *
     * @return string
     */
    private function generatePayload(): string
    {
        $staffs = $this->externalUser->getStaffContactListExtended();

        /* Manufacture a portrait url
         * FIXME You might not need this, because you could infer it on frontend.
         */
        foreach ($staffs as &$staff) {
            $staff['portrait_url'] = sprintf('/portrait.php?userId=%s&size=square32', $staff['StaffID']);
        }

        unset($staff);

        return json_encode($staffs);
    }
}
