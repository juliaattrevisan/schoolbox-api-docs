<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Group;

use Schoolbox\Permission\Access\UserAccess;

class ModifyAccessLevel extends GroupMember
{
    /**
     * Constructor
     *
     * @param int $folderId
     */
    public function __construct(int $folderId)
    {
        parent::__construct($folderId);

        // This request is JSON
        $this->setAccepts('application/json');
        $this->setContentType('application/json');
    }

    /**
     * This is always PATCH
     *
     * @return string
     */
    public function getMethod(): string
    {
        return 'PATCH';
    }

    /**
     * Set access level for members.
     *
     * @param int $user_id
     * @param int $accessLevel
     * @return void
     */
    public function setMemberAccessLevel(int $user_id, int $accessLevel): void
    {
        // Stack access types with previously set ones
        $existingParam = $this->getFormParam('accessTypes') ?? [];
        $this->setFormParam(
            'accessTypes',
            $existingParam + [
                $user_id => [
                    'userId'     => $user_id,
                    'accessType' => UserAccess::ACCESS_LEVEL_TO_NAME[$accessLevel],
                ],
            ],
        );
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/group/updateMembers/{$this->folderId}";
    }
}
