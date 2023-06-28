<?php

namespace Tests\Support\Payload\Badge;

use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\PostTrait;

class AwardAchievement extends \Tests\Support\Payload\Request
{
    use PostTrait;
    use FormTrait;

    private int $badgeId;

    public function __construct(
        int $badgeId,
        int $externalId,
        string $firstName,
        int $id,
        string $lastName,
        string $name,
        string $role
    ) {
        $array = array();
        $array[] = array(
            'external_id' => $externalId,
            'first_name' => $firstName,
            'id' => $id,
            'last_name' => $lastName,
            'name' => $name,
            'role' => $role
        );

        $this->setReferer("/learning/badge?22=&23=&24=&26=");
        $this->setFormParam('users', $array);
        $this->badgeId = $badgeId;
        $this->setHeader('content-type', 'application/json');
    }

    protected function getUrl(): string
    {
        return "/learning/badge/award/" . $this->badgeId . "/achievement";
    }
}
