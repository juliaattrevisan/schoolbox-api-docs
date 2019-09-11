<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Course\Specification;

use Payload\PostTrait;
use Payload\Request;

class Create extends Request
{
    use PostTrait;

    public function __construct()
    {
        $this->setReferer('/course/new');

        $this->setFormParam('name','');
        $this->setFormParam('parent','');
        $this->setFormParam('curriculumCodes[0]','');
        $this->setFormParam('part1',1);
    }

    public function setName(string $name): void
    {
        $this->setFormParam('name',$name);
    }

    public function setParent(int $parent_folder_id): void
    {
        $this->setFormParam('parent',$parent_folder_id);
    }

    public function getUrl(): string
    {
        return '/course/new';
    }

}
