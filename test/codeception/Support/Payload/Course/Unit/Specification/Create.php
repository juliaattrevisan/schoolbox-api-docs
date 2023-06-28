<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Course\Unit\Specification;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

class Create extends Request
{
    use PostTrait;

    private $courseSpecId;

    public function __construct(int $courseSpecId)
    {
        $this->courseSpecId = $courseSpecId;

        $this->setReferer("/course/modify/$courseSpecId");
        $this->setFormParam('name', '');
    }

    public function setName(string $name): void
    {
        $this->setFormParam('name', $name);
    }


    public function getUrl(): string
    {
        return "/course/{$this->courseSpecId}/unitspecs/new";
    }
}
