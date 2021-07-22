<?php


namespace Payload\Badge;


use Payload\PostTrait;

class RemoveGoal extends \Payload\Request
{
    use PostTrait;

    private int $badgeId;

    public function __construct(int $badgeId, string $userId)
    {

        $this->badgeId = $badgeId;
        $this->setFormParam('userIds[0]', $userId);
        $this->setReferer("/learning/badge");
        $this->setHeader('content-type', 'application/x-www-form-urlencoded');
    }


    protected function getUrl(): string
    {
        return "/learning/badge/award/$this->badgeId/goal/withdraw";
    }
}
