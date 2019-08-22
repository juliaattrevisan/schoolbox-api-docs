<?php


namespace Payload;


class RubricIndicator extends Base
{
    private $homepage_id;

    public function __construct()
    {
        Base::__construct();

        $this->homepage_id = '??';
        $this->setPage('/rubric');
        $this->setReferer('/learning/folders/' . $this->homepage_id . '/new?returnUrl=');
        $this->setFormParam('type', 'criterion');
        $this->setFormParam('rubricId', 0);
        $this->setFormParam('groupId', '');
        $this->setFormParam('groupIndex', 0);
        $this->setFormParam('name', '??');
        $this->setFormParam('levelCount', 3);
        $this->setFormParam('folderId', $this->homepage_id);
        $this->setFormParam('markPerCapability', true);
        $this->setFormParam('groupName', 'someGroup');
    }

    public function setName(string $name)
    {
        $this->setFormParam('name', $name);
    }

    public function setGroupName(string $groupName)
    {
        $this->setFormParam('groupName', $groupName);
    }

    public function setFolderId(string $folderId)
    {
        $this->homepage_id = $folderId;
        $this->setReferer('/learning/folders/' . $this->homepage_id . '/new?returnUrl=');
        $this->setFormParam('folderId', $this->homepage_id);
    }
}