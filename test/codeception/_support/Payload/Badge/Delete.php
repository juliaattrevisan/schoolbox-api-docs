<?php


namespace Payload\Badge;


use Payload\DeleteTrait;
use Payload\Pastoral\FormTrait;

class Delete extends \Payload\Request
{

    use DeleteTrait;
    use FormTrait;

    private int $badgeId;

    public function __construct(int $badgeId)
    {

        $this->badgeId = $badgeId;
        $file= null;
        $this->setReferer("/learning/badge");
        $this->setHeader('content-type','application/json');
    }


    protected function getUrl(): string
    {
        return "/learning/badge/$this->badgeId";
    }
}
