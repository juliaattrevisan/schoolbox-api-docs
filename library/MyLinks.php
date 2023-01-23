<?php

class MyLinks extends DeprecatedModel
{
    public function __construct()
    {
        $this->_table = 'bookmarks';
        $this->_index = 'id';

        parent::__construct();
    }

    /**
     * @param int    $uid
     * @param string $relativeLink
     * @param string $absoluteLink
     * @return mixed
     */
    public function linkExists(int $uid, string $relativeLink, string $absoluteLink)
    {
        $query = "SELECT id FROM bookmarks WHERE owner='$uid' AND (url = '" . $this->db->quote($relativeLink) . "' OR url = '" . $this->db->quote($absoluteLink) . "')";
        return $this->db->getOne($query);
    }
}
