<?php

use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;

/**
 * Class MyFile
 * @deprecated
 */
class MyFile extends DeprecatedModel
{

    /**
     * @var int
     */
    private $owner;


    /**
     * MyFile constructor.
     * @deprecated
     *
     * @param int $owner
     */
    function __construct($owner = 0)
    {
        if ($owner == 0) {
            $this->owner = $_SESSION['id'];
        } else {
            $this->owner = $owner;
        }

        $this->_table = "myFile";
        $this->_index = "id";

        parent::__construct();
    }


    /**
     * @deprecated
     *
     * @param      $folderID
     * @param bool $config
     * @return array|bool
     */
    public function getAllInFolder($folderID, $config = false)
    {
        $query = "
            SELECT f.*, f.submitted, af.iconFile FROM " . $this->_table . " f
            LEFT JOIN storage s ON f.hash = s.hash
            LEFT JOIN allowedFileExtension afe ON s.ext = afe.extension
            LEFT JOIN allowedFileTypes af ON afe.FileTypeID = af.FileTypeID
            WHERE owner = " . $this->owner . " AND parent = " . (int)$folderID . " AND deleted = 0";

        if ($config['published'] == true) {
            $query .= " AND published IS NOT NULL";
        }

        if (!empty($config['filter']) && $config['filter'][0] != 'all') {

            $config['filter'] = is_array($config['filter']) ? $config['filter'] : [$config['filter']];

            $query .= " AND ( 1=2 ";

            $filter_sql = [
                'document' => " OR ( afe.mime LIKE '%pdf%' OR afe.mime LIKE '%msword' OR afe.mime LIKE '%officedoc%' OR afe.mime LIKE '%rtf' OR afe.mime LIKE 'text%' OR afe.mime LIKE '%document%' OR afe.mime LIKE '%powerpoint%') ",
                'image'    => " OR ( afe.mime LIKE '%image%' ) ",
                'audio'    => " OR ( afe.mime LIKE '%audio%' ) ",
                'video'    => " OR ( afe.mime LIKE '%video%' OR afe.mime LIKE '%flash%' OR afe.mime LIKE '%realmedia%' ) ",
                'other'    => " OR ( afe.mime LIKE 'application%' AND afe.mime NOT LIKE '%pdf' AND afe.mime NOT LIKE '%msword' AND afe.mime NOT LIKE '%rtf' AND afe.mime NOT LIKE 'text%' AND afe.mime NOT LIKE '%document%' AND afe.mime NOT LIKE '%powerpoint%') ",
            ];

            foreach ($config['filter'] as $filter) {
                if (array_key_exists($filter, $filter_sql)) {
                    $query .= $filter_sql[$filter];
                } else {
                    $query .= " OR ( afe.mime = '$filter ' ) ";
                }
            }

            $query .= ") ";
        }

        return $this->db->getAll($query);
    }


    /**
     * @deprecated
     *
     * @param $fileID
     * @param $action
     */
    public function logAction($fileID, $action)
    {
        $logEntry = [
            'object'   => 'myfile',
            'objectId' => $fileID,
            'action'   => $action,
            'user'     => $_SESSION['id'],
        ];

        $notif = Notifactory::create(Notif::TOPIC_CMS);
        $notif->info("CRUD", $logEntry);
    }


    /**
     * @deprecated
     *
     * @param $fileID
     * @return bool
     */
    public function delete($fileID)
    {
        $this->logAction($fileID, "delete");
        return parent::update([
            'id'         => $fileID,
            'deleted_at' => Alaress_Date_Format::now()->getDatabase(),
        ]);
    }


    /**
     * @deprecated
     *
     * @param $parentID
     */
    public function deleteByParent($parentID)
    {
        $fileList = $this->getAllInFolder($parentID);
        if (is_array($fileList)) {
            foreach ($fileList as $file) {
                $this->delete($file['id']);
            }
        }
    }
}
