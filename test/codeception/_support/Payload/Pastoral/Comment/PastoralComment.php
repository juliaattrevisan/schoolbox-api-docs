<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Pastoral\Comment;

use Payload\PostTrait;
use Payload\Request;
use Schoolbox\Pastoral;

class PastoralComment extends Request
{
    use PostTrait;

    private $pastoral;

    public function __construct(Pastoral\Model $pastoral)
    {
        $this->pastoral = $pastoral;

        $this->setReferer("/pastoral/student/{$pastoral->getStudent()->getId()}/record/{$pastoral->getId()}");
        $this->setFormParam('pastoral_id', $pastoral->getId());
        $this->setFormParam('student_id', $pastoral->getStudent()->getId());
        $this->setFormParam('comment_body', '');
    }

    public function setPublicComment(string $comment): void
    {
        $this->setFormParam('comment_body', $comment);
    }

    public function setPrivateComment(string $comment): void
    {
        $this->setFormParam('comment_private', 1);
        $this->setFormParam('comment_body', $comment);
    }

    protected function getUrl(): string
    {
        return "/pastoral/record/{$this->pastoral->getId()}/comment/insert";
    }
}
