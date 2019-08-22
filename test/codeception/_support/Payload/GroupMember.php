<?php


namespace Payload;


class GroupMember extends Base
{

    const READ_PERMISSION = 1;
    const WRITE_PERMISSION = 2;
    const ADMIN_PERMISSION = 3;

    public static function getUserPermissions(): array
    {
        return [
            'read' => self::READ_PERMISSION,
            'write' => self::WRITE_PERMISSION,
            'admin' => self::ADMIN_PERMISSION
        ];
    }

    public function __construct()
    {
        Base::__construct();
        $this->action = 'create';
        $this->parent_id = 0;
        $this->setPage('/group/modifyForm.php');
        $this->setReferer('/group/modify/??');
        $this->setFormParam('categoryID', '??');
    }

    public function resetMemberAdjustmentList()
    {
        $this->removeFormParam('userID');
        $this->removeFormParam('accessLevel');
    }

    public function setFolderGroup(int $folder_id)
    {
        $this->setFormParam('categoryID', $folder_id);
        $this->setReferer('/group/modify/'.$folder_id);
    }

    public function setNewMember(int $user_id)
    {
        $current_member_key = count($this->getFormParam('userID')) - 1;
        $this->setFormParam('userID['.($current_member_key+1).']', $user_id);
    }

    public function setNewMemberPermission(int $user_id, string $permission_level)
    {
        $this->setFormParam('accessLevel['.$user_id.']', self::getUserPermissions()[$permission_level]);
    }
}