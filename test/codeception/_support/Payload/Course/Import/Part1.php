<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Course\Import;

use Payload\PostTrait;
use Payload\Request;

class Part1 extends Request
{
    use PostTrait;

    public function __construct(int $courseSpecId, int $folderId, int $componentId, string $returnUrl)
    {
        $this->setReferer("/course/import/$courseSpecId/folder/$folderId/$componentId?returnUrl=$returnUrl");

        $this->setCourseSpecId($courseSpecId);
        $this->setFormParam('import_folders', $folderId);
        $this->setFormParam('component_id', $componentId);
        $this->setFormParam('part-1', 1);
    }

    public function setCourseSpecId(int $course_spec_id): void
    {
        $this->setFormParam('course_spec_id', $course_spec_id);
    }

    protected function getUrl(): string
    {
        return '/course/import/classes';
    }
}
