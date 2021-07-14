<?php

namespace Payload\Badge;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class Modify extends \Payload\Request
{
    use PostTrait;
    use FormTrait;

    private int $badgeId;

    public function __construct(
        int $badgeId,
        string $badgeavatarselector,
        int $category,
        string $body,
        string $name,
        bool $allowasgoal,
        ?int $achievementExpiry,
        ?int $goalExpiry
    ) {
        $this->badgeId = $badgeId;
        $this->setReferer("/learning/badge/$badgeId/update");
        $this->setFormParam('allow-as-goal', $allowasgoal);
        $this->setFormParam('badge-avatar-selector', $badgeavatarselector);
        $this->setFormParam('body', $body);
        $this->setFormParam('category', ['id' => $category]);
        $this->setFormParam('name', $name);
        $this->setFormParam('achievementExpiry', $achievementExpiry);
        $this->setFormParam('goalExpiry', $goalExpiry);

        $this->setHeader('content-type', 'application/json');
    }

    protected function getUrl(): string
    {
        return "learning/badge/$this->badgeId/update";
    }
}
