<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Resource\Folder;

use Schoolbox\Resource\Folder;
use Schoolbox\Resource\HomepageTemplate;

/**
 * Encapsulates a folder UPDATE request
 */
class Update extends Modify
{
    /** @var Folder\Model */
    private $folder;

    /**
     * @param Folder\Model $folder
     * @param array        $rolePerms
     */
    public function __construct(Folder\Model $folder, array $rolePerms)
    {
        // Default form on page load
        $this->setName($folder->getName());
        $this->setTemplate(HomepageTemplate::HOMEPAGE_KEEP_EXISTING);
        $colour = $folder->getColour();
        $this->setColour($colour ? $colour->getAsRgb() : '');
        $coverImage = $folder->getCoverImage();
        $this->setCoverImage($coverImage ? $coverImage->getHash() : '');


        if ($folder->getInherit()) {
            $this->setInherit(true);
        } else {
            foreach($rolePerms as $roleId => $accessLevel){
                $this->setRoleAccessLevel($roleId, $accessLevel);
            }
            $this->setClassCodes($folder->getCodeString());
        }
        $this->setYearLevelIds(implode(',', $folder->getYearLevels()->getPropertyValues('id')));
        $this->setCurriculumCodes($folder->getCurriculumNodes()->getPropertyValues('id'));

        $this->folder = $folder;
    }


    /** @inheritDoc */
    public function getUrl(): string
    {
        return "/resource/folder/{$this->folder->getId()}/update";
    }
}
