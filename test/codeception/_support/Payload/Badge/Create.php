<?php

namespace Payload\Badge;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class Create extends \Payload\Request
{
    use PostTrait;
    use FormTrait;

    public function __construct(
        string $badgeavatarselector,
        int $category,
        string $body,
        string $name,
        bool $allowasgoal,
        ?int $achievementExpiry,
        ?int $goalExpiry
    ) {
        $this->setReferer("/learning/badge/create");
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
        return "/learning/badge";
    }
}
