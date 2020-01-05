<?php

use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;

/**
 * Class MyFolder
 */
class MyFolder extends DeprecatedModel
{

    /**
     * @var int
     */
    private $owner;


    /**
     * MyFolder constructor.
     * @param int $owner
     */
    public function __construct($owner = 0)
    {
        if ($owner == 0) {
            $this->owner = $_SESSION['id'];
        } else {
            $this->owner = $owner;
        }

        $this->_table = "myFolder";
        $this->_index = "id";

        parent::__construct();
    }


    /**
     * @param int $parent
     * @return array|bool
     */
    public function getFolderList($parent = 0)
    {
        $fields = [
            'owner'  => $this->owner,
            'parent' => $parent,
        ];

        return $this->getByArray($fields);
    }


    /**
     * @param $id
     * @return mixed
     */
    public function getParent($id)
    {
        $query = "SELECT parent FROM " . $this->_table . " WHERE id = " . (int)$id;
        return $this->db->getOne($query);
    }


    /**
     * @param int $folderID
     * @return array|bool
     */
    public function getFolderNode($folderID = 0)
    {

        // get folder contents
        $folderList = $this->getFolderList($folderID);

        if ($folderID > 0) {
            // get parent folder contents
            $siblingList = $this->getFolderNode($this->getParent($folderID));

            if (is_array($folderList)) {
                // find index of parent node in siblingList
                $total = count($siblingList);
                for ($i = 0; $i < $total; $i++) {
                    if ($siblingList[$i]['id'] == $folderID) {
                        break;
                    }
                }

                // insert folder contents next to parent node
                array_splice($siblingList, $i + 1, 0, $folderList);
            }
            return $siblingList;
        } else {
            return $folderList;
        }
    }


    /**
     * @param int  $folderID
     * @param int  $depth
     * @param bool $ignore
     * @return array|bool
     */
    public function getFolderTree($folderID = 0, $depth = 0, $ignore = false)
    {

        $folderList = $this->getFolderList($folderID);
        $output     = [];

        if (is_array($folderList)) {
            $total = count($folderList);
            for ($i = 0; $i < $total; $i++) {
                if ($folderList[$i]['id'] != $ignore) {

                    $childrenList = $this->getFolderTree($folderList[$i]['id'], $depth + 1, $ignore);

                    $output[] = [
                        'id'       => $folderList[$i]['id'],
                        'name'     => $folderList[$i]['name'],
                        'parent'   => $folderList[$i]['parent'],
                        'depth'    => $depth,
                        'isParent' => $hasChildren = is_array($childrenList),
                    ];


                    if ($hasChildren) {
                        $output = array_merge($output, $childrenList); //array_splice($folderList, $i+1, 0, $childrenList);
                    }
                }
            }

            return $output;
        }
        return false;
    }


    /**
     * @param $folderID
     * @return mixed
     */
    public function hasChildren($folderID)
    {
        $query = "SELECT f.id as children
                  FROM myFolder f
                  WHERE parent = {$this->db->quote($folderID)}
                  LIMIT 1";

        return $this->db->getOne($query);
    }


    /**
     * @param $folderID
     * @param $action
     */
    public function logAction($folderID, $action)
    {
        $logEntry = [
            'object'   => 'myfolder',
            'objectId' => $folderID,
            'action'   => $action,
            'user'     => $_SESSION['id'],
        ];

        $notif = Notifactory::create(Notif::TOPIC_CMS);
        $notif->info("CRUD", $logEntry);
    }


    /**
     * @param $data
     * @return int|string
     */
    public function create($data)
    {
        $data['owner'] = $this->owner;
        $folderID      = parent::create($data);

        $this->logAction($folderID, "create");

        return $folderID;
    }


    /**
     * @param $data
     * @return bool
     */
    public function update($data)
    {
        $this->logAction($data['id'], "modify");
        return parent::update($data);
    }


    /**
     * @param $folderID
     * @return bool
     */
    public function delete($folderID)
    {
        // delete files
        $fileHandler = new MyFile;
        $fileHandler->deleteByParent($folderID);

        $this->logAction($folderID, "delete");
        return parent::delete($folderID);
    }
}
