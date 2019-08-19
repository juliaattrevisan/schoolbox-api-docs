<?php


namespace Payload;


class RubricCapability extends Base
{
    private $homepage_id;

    public function __construct()
    {
        Base::__construct();

        $this->homepage_id = '??';
        $this->setPage('/rubric');
        $this->setReferer('/learning/folders/' . $this->homepage_id . '/new?returnUrl=');
        $this->setFormParam('type', 'group');
        $this->setFormParam('rubricId', 0);
        $this->setFormParam('groupId', '');
        $this->setFormParam('groupIndex', '??');
        $this->setFormParam('name', '??');
        $this->setFormParam('levelCount', 3);
        $this->setFormParam('folderId', $this->homepage_id);
        $this->setFormParam('markPerCapability', true);
    }

    public function setName(string $name)
    {
        $this->setFormParam('name', $name);
    }

    public function setGroupIndex(string $index)
    {
        $this->setFormParam('groupIndex', $index);
    }

    public function setFolderId(string $folderId)
    {
        $this->homepage_id = $folderId;
        $this->setReferer('/learning/folders/' . $this->homepage_id . '/new?returnUrl=');
        $this->setFormParam('folderId', $this->homepage_id);
    }
}



