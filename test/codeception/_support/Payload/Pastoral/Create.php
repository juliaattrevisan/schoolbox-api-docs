<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Pastoral;

use Payload\PostTrait;
use Payload\Request;

class Create extends Request
{
    use PostTrait;
    use FormTrait;

    public function __construct(int $studentId)
    {
        $this->setReferer("/pastoral/student/$studentId/record/insert");

        $this->setFormParam('id', '');
        $this->setFormParam('student_id', $studentId);
        $this->setHeading('');
        $this->setFormParam('typeId', '1');
        $this->setFormParam('subtypeId', '1');
        $this->setFormParam('severityId', '1');
        $this->setFormParam('points', '');
        $this->setFormParam('tags', '');
        $this->setFormParam('body_public', '');
        $this->setFormParam('body_private', '');
    }

    protected function getUrl(): string
    {
        return '/pastoral/record/insert';
    }
}
