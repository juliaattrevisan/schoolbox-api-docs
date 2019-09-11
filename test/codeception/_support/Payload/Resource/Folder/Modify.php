<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Resource\Folder;

use Payload;
use Payload\PostTrait;

/**
 * Common implementation of folder CREATE + UPDATE requests
 */
abstract class Modify extends Payload\Request
{
    public const TEMPLATE_BLANK = 9;

    use PostTrait;

    public function setName(string $name): void
    {
        $this->setFormParam('name', $name);
    }

    public function setTemplate(int $templateId): void
    {
        $this->setFormParam('template', (string)$templateId);
    }

    public function setColour(string $colour): void
    {
        $this->setFormParam('colour', $colour);
    }

    public function setCoverImage(string $coverImage): void
    {
        $this->setFormParam("attachment[]", $coverImage);
    }

    public function setInherit(bool $inherit): void
    {
        if($inherit){
            $this->setFormParam('inherit', '1');
        } else {
            $this->unsetFormParam('inherit');
        }
    }

    public function setRoleAccessLevel(int $roleId, int $accessLevel): void
    {
        $this->setFormParam("group[$roleId]", (string)$accessLevel);
    }

    public function setCreateGroup(bool $createGroup): void
    {
        if($createGroup){
            $this->setFormParam('groupcheck', '1');
        } else {
            $this->unsetFormParam('groupcheck');
        }
    }

    public function setClassCodes(string $classCodes): void
    {
        $this->setFormParam("code", $classCodes);
    }

    public function setYearLevelIds(string $yearLevelIds): void
    {
        $this->setFormParam("yearLevelIds", $yearLevelIds);
    }

    public function setCurriculumCodes(array $curriculumCodes): void
    {
        // Form always inserts an empty item
        array_unshift($curriculumCodes, '');
        $this->setFormParam("curriculumCodes", $curriculumCodes);
    }
}
