<?php

class TextBox extends DeprecatedModel {

    function TextBox() {
        $this->_table = 'textBox';
        $this->_index = 'textBoxID';

        parent::DeprecatedModel();
    }

    function getAllByCategory($categoryID) {
        $query = "SELECT * FROM " . $this->_table . " WHERE categoryID = $categoryID AND deleted = 'false' ORDER BY heading";
        return $this->db->getAll($query);
    }

    function getCurrent($categoryID) {
        $textBoxList = $this->getAllByCategory($categoryID);
        return $textBoxList[0];
    }

    function delete($textBoxID) {
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
     * @param  $cid
     * @return void
     */
    public function homepageClean($cid) {
        $query = "UPDATE {$this->_table} SET deleted = 'true' WHERE categoryID = '{$cid}'";

        $this->db->query($query);
    }

    public function migrateCategory($sourceCategoryId, $destinationCategoryId) {
        $textBoxList = $this->getAllByField('categoryID', $sourceCategoryId);

        if (is_array($textBoxList)) {
            foreach ($textBoxList as &$textBox) {
                unset($textBox['textBoxID']);

                $textBox['categoryID'] = $destinationCategoryId;

                $textBox['textBoxID'] = parent::create($textBox);
            }
        }
    }

    /**
     * Search the user table for particular user(s)
     *
     * @param $keyword
     * @param int $offset
     * @param int $limit
     * @param null $count
     * @param string $sort_by
     * @param string $sort_dir
     * @return array|bool
     */
    public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'cname', $sort_dir = 'ASC') {

        // TODO add full search capabilities, currently just used by ajax
        $query = "SELECT textBoxID, categoryID, heading, body, category.name as cname
                     FROM " . $this->_table . "
                    JOIN category ON categoryID = category.id
                  WHERE textBox.deleted = 'false' AND heading like '%" . mysql_real_escape_string($keyword) . "%'
                  ORDER BY $sort_by $sort_dir
                  LIMIT $offset, $limit";

        return $this->db->getAll($query);
    }


}
