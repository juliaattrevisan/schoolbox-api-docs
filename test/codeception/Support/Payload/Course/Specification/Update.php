<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Course\Specification;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;

class Update extends Request
{
    use PostTrait;

    private $courseSpecId;

    public function __construct(CourseSpec\Model $courseSpec)
    {
        $this->courseSpecId = $courseSpec->getId();

        $this->setReferer("/course/modify/{$courseSpec->getId()}");

        $this->setFormParam('name', $courseSpec->getTitle());
        $this->setFormParam('parent', $courseSpec->getFolder()->getParent()->getId());
        $this->setFormParam('curriculumCodes[0]', '');
        $this->setFormParam('newUnitName', '');
        $this->setFormParam('template', 1);
        $this->setFormParam('submit', 'Save Course');
    }

    public function addUnitSpec(int $unitSpecId, string $unitName): void
    {
        $this->setFormParam('unit[' . $unitSpecId . '][name]', $unitName);
    }

    public function getUrl(): string
    {
        return "/course/modify/{$this->courseSpecId}";
    }
}
