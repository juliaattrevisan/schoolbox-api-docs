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
        $quoter = $this->db->getQuoter();
        $query  = "
            SELECT *, UNIX_TIMESTAMP(close) close
            FROM {$quoter->quoteIdentifier($this->_table)}
            WHERE {$quoter->quoteIdentifier($this->_index)} = ?
        ";
        return $this->db->query($query, [$pollID])->fetch();
    }

    public function getAllByCategory($folder_id)
    {
        $query = "SELECT * FROM poll WHERE folder_id = ? ORDER BY created DESC";
        return $this->db->query($query, [$folder_id])->fetchAll();
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
        $date   = Alaress_Date_Format::now();
        $params = [$pollID, $_SESSION['id']];
        switch ($poll['frequency']) {
            case "hourly":
                $params[] = $date->addHours(-1)->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT);
                break;
            case "daily":
                $params[] = $date->addDays(-1)->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT);
                break;
        }

        $query = "
            SELECT count(*)
            FROM pollLog
            WHERE poll_id = ? AND user_id = ? AND created > ?";
        $vote  = $this->db->query($query, $params)->fetchColumn();

        if ($vote > 0) {
            return true;
        }
        return false;
    }

    public function open($pollID)
    {
        $query = "SELECT count(*) FROM poll WHERE id = ? AND close > NOW()";
        $vote  = $this->db->query($query, [$pollID])->fetchColumn();
        if ($vote > 0) {
            return true;
        }
        return false;
    }

    public function placeVote($pollID, $vote)
    {
        if (is_numeric($pollID) && is_numeric($vote) && $vote >= 0 && $vote < 10 && !$this->hasVoted($pollID)) {
            $column = $this->db->getQuoter()->quoteIdentifier('result_' . $vote);
            $query = "UPDATE poll SET {$column} = {$column} + 1 WHERE id = ?";
            $this->db->query($query, [$pollID]);
            $query = "INSERT INTO pollLog SET poll_id = ?, user_id = ?, created = NOW()";
            $this->db->query($query, [$pollID, $_SESSION['id']]);
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
        $query  = "
            DELETE l
            FROM poll p
            LEFT JOIN pollLog l ON l.poll_id = p.id
            WHERE p.folder_id = ?
        ";
        $this->db->query($query, [$cid]);

        $query = "DELETE FROM poll WHERE folder_id = ?";
        $this->db->query($query, [$cid]);
    }

    /**
     * @deprecated
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $query = "DELETE FROM pollLog WHERE poll_id = ?";
        $this->db->query($query, [$id]);

        $query = "DELETE FROM poll WHERE id = ?";
        $this->db->query($query, [$id]);
        return true;
    }
}
