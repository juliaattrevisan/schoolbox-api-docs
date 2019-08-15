<?php


namespace Payload;


class Folder extends Base
{
    const NO_PRIVLEDGES = '0';
    const READ_PRIVLEDGES = '1';
    const WRITE_PRIVLEDGES = '2';

    const TEMPLATE_HOMEPAGE_ID=9;

    public static function getFolderPermissions(): array
    {
        return [
            'none' => self::NO_PRIVLEDGES,
            'read' => self::READ_PRIVLEDGES,
            'write' => self::WRITE_PRIVLEDGES
        ];
    }

    private $action;
    private $parent_id;
    public function __construct()
    {
        Base::__construct();
        $this->action='create';
        $this->parent_id=0;
        $this->setPage('/resource/folder/update');
        $this->setReferer('/resource/folder/'.$this->action.'/'.$this->parent_id);
        $this->setFormParam('parent', $this->parent_id);
        $this->setFormParam('categoryID', null);
        $this->setFormParam('name', 'sometitle');
        $this->setFormParam('template', -1);
        $this->setFormParam('group[1]', '2');
        $this->setFormParam('group[2]', '1');
        $this->setFormParam('group[3]', '1');
        $this->setFormParam('group[4]', '1');
        $this->setFormParam('group[5]', '1');
        $this->setFormParam('group[6]', '0');
        $this->setFormParam('code', '');
        $this->setFormParam('curriculumCodes[]', '');
    }

    public function setName(string $name)
    {
        $this->setFormParam('name', $name);
    }

    public function setTemplate(int $template_id)
    {
        $this->setFormParam('template', $template_id);
    }

    public function setParentFolder(int $parent_folder_id)
    {
        $this->parent_id=$parent_folder_id;
        $this->setReferer('/resource/folder/create/'.$this->parent_id);
        $this->setFormParam('parent', $this->parent_id);
        if ($this->parent_id == 0)
        {
            $this->removeFormParam('inherit');
        } else {
            $this->setFormParam('inherit', 1);
        }
    }

    public function setRolePermission(int $role_id, $permission_level)
    {
        $this->setFormParam('group['.$role_id.']', self::getFolderPermissions()[$permission_level] );
    }


}