<?php

class TextBox extends DeprecatedModel implements \Schoolbox\IHomepageCleanable
{

    function __construct()
    {
        $this->_table = 'textBox';
        $this->_index = 'textBoxID';

        parent::__construct();
    }

    function getAllByCategory($categoryID)
    {
        $query = "SELECT * FROM " . $this->_table . " WHERE categoryID = $categoryID AND deleted = 'false' ORDER BY heading";
        return $this->db->getAll($query);
    }

    function getCurrent($categoryID)
    {
        $textBoxList = $this->getAllByCategory($categoryID);
        return $textBoxList[0];
    }

    function delete($textBoxID)
    {
        $data = array(
            'textBoxID' => $textBoxID,
            'deleted' => 'true',
        );
        return $this->update($data);
    }


    /**
     * Soft-deletes content
     *
     * TODO: Refactor
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid)
    {
        $query = "UPDATE {$this->_table} SET deleted = NOW() WHERE categoryID = '{$cid}'";

        $this->db->query($query);
    }


    /**
     * Search the user table for particular user(s)
     *
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @param null $count
     * @param string $sort_by
     * @param string $sort_dir
     * @return array|bool
     */
    public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'cname', $sort_dir = 'ASC')
    {

        // TODO add full search capabilities, currently just used by ajax
        $query = "SELECT textBoxID, categoryID, heading, body, folder.name as cname
                     FROM " . $this->_table . "
                    JOIN folder ON categoryID = folder.id
                  WHERE textBox.deleted = 'false' AND heading like '%" . $this->db->quote($keyword) . "%'
                  AND folder.deleted_at IS NULL
                  ORDER BY $sort_by $sort_dir
                  LIMIT $offset, $limit";

        return $this->db->getAll($query);
    }
}
