<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

class PastoralComment extends Request
{
    public function __construct()
    {
        $this->setPage('/pastoral/record/??/comment/insert');
        $this->setReferer('/pastoral/student/??/record/??');
        $this->setFormParam('pastoral_id', '??');
        $this->setFormParam('student_id', '??');
        $this->setFormParam('comment_body', 'some comments');
    }

    public function setStudentAndRecordIds(int $student_id, int $record_id)
    {
        $this->setPage('/pastoral/record/'.$record_id.'/comment/insert');
        $this->setReferer('/pastoral/student/'.$student_id.'/record/'.$record_id);
        $this->setFormParam('pastoral_id', $record_id);
        $this->setFormParam('student_id', $student_id);
    }

    public function setPublicComment(string $comment)
    {
        $this->removeFormParam('comment_private');
        $this->setFormParam('comment_body', $comment);
    }

    public function setPrivateComment(string $comment)
    {
        $this->setFormParam('comment_private', 1);
        $this->setFormParam('comment_body', $comment);
    }
}
