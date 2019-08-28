<?php
declare(strict_types=1);

namespace Payload\Resource\Folder;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\HomepageTemplate;

/**
 * Encapsulates a folder CREATE request
 */
class Create extends Modify
{
    /** @var int */
    private $parentId;

    /**
     * @param int $parentId Defaults to creating a top-level folder
     */
    public function __construct(int $parentId = Folder\Model::ROOT_ID)
    {
        // Default form on page load
        $this->setName('');
        $this->setTemplate(HomepageTemplate::HOMEPAGE_NONE);
        $this->setColour('');
        $this->setCoverImage('');
        foreach ([1, 2, 3, 4, 5, 6] as $roleId) {
            $this->setRoleAccessLevel($roleId, UserAccess::ACCESS_DENY);
        }
        if ($parentId === Folder\Model::ROOT_ID) {
            $this->setCreateGroup(false);
            $this->setClassCodes('');
        } else {
            $this->setInherit(true);
        }
        $this->setYearLevelIds('');
        $this->setCurriculumCodes([]);

        $this->parentId = $parentId;
    }


    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource/folder/{$this->parentId}/create";
    }
}
