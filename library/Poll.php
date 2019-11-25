<?php

class Poll extends DeprecatedModel implements \Schoolbox\IHomepageCleanable
{
    public function __construct()
    {

        $this->_table = "poll";
        $this->_index = "id";

        parent::__construct();
    }

    public function getByID($pollID)
    {
        $query = "SELECT *, UNIX_TIMESTAMP(close) close FROM " . $this->_table .
            " WHERE " . $this->_index . " = '{$this->db->quote($pollID)}'";
        return $this->db->getRow($query);
    }

    public function getAllByCategory($folder_id)
    {
        $query = "SELECT * FROM poll WHERE folder_id = '{$this->db->quote((string)$folder_id)}' ORDER BY created DESC";
        return $this->db->getAll($query);
    }

    public function getData($pollID)
    {
        $poll = $this->getByID($pollID);
        if (!$poll['id']) {
            return false;
        }
        $data  = [];
        $total = 0;
        for ($i = 0; $i < 10; $i++) {
            if (!empty($poll['option_' . $i])) {
                $set['option'] = $poll['option_' . $i];
                $set['result'] = $poll['result_' . $i];
                $data[]        = $set;
                $total         += $set['result'];
            }
        }

        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $data[$i]['percentage'] = $total ? number_format(($data[$i]['result'] / $total) * 100, 2) : 0;
        }

        return $data;
    }

    public function hasVoted($pollID)
    {
        $poll   = $this->getById($pollID);
        $cutoff = "";
        $date = Alaress_Date_Format::now();
        switch ($poll['frequency']) {
            case "hourly":
                $cutoff = "AND created > '{$this->db->quote($date->addHours(-1)->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT))}'";
                break;
            case "daily":
                $cutoff = "AND created > '{$this->db->quote($date->addDays(-1)->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT))}'";
                break;
        }

        $query = "SELECT count(*) FROM pollLog WHERE poll_id = '{$this->db->quote($pollID)}' $cutoff AND user_id = '{$this->db->quote($_SESSION['id'])}'";
        $vote  = $this->db->getOne($query);

        if ($vote > 0) {
            return true;
        }
        return false;
    }

    public function open($pollID)
    {
        $query = "SELECT count(*) FROM poll WHERE id = '{$this->db->quote($pollID)}' AND close > NOW()";
        $vote  = $this->db->getOne($query);
        if ($vote > 0) {
            return true;
        }
        return false;
    }

    public function placeVote($pollID, $vote)
    {
        if (is_numeric($pollID) && is_numeric($vote) && $vote >= 0 && $vote < 10 && !$this->hasVoted($pollID)) {
            $query = "UPDATE poll SET result_" . $vote . " = result_" . $vote . " + 1 WHERE id = '{$this->db->quote($pollID)}'";
            $this->db->query($query);
            $query = "INSERT INTO pollLog SET poll_id = '{$this->db->quote($pollID)}', user_id = '{$this->db->quote($_SESSION['id'])}', created = NOW()";
            $this->db->query($query);
            return true;
        }
        return false;
    }

    public function hasResults($pollID)
    {
        $poll = $this->getByID($pollID);

        $total = 0;
        for ($i = 0; $i < 10; $i++) {
            $total += $poll["result_{$i}"];
        }

        return ($total > 0);
    }

    public function homepageClean($cid)
    {
        $query = "DELETE l FROM  `" . $this->_table . "` p LEFT JOIN pollLog l ON l.poll_id = p.id WHERE p.folder_id='{$this->db->quote($cid)}'";
        $this->db->query($query);

        $query = "DELETE FROM poll WHERE folder_id = '{$this->db->quote($cid)}'";
        $this->db->query($query);
    }

    /**
     * @deprecated
     * @param $id
     * @return bool
     */
    public function delete($id)
    {
        $query = "DELETE FROM pollLog WHERE poll_id = '{$this->db->quote($id)}'";
        $this->db->query($query);

        $query = "DELETE FROM poll WHERE id = '{$this->db->quote($id)}'";
        return $this->db->query($query);
    }
}
