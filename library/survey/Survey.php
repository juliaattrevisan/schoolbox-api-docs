<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/Evaluation.php');

class Survey extends Evaluation {
    const ONLINE_ASSESSMENT_TYPE_ID = 11;
    const UNLIMITED_DURATION = -1;

    /**
     * A list of assessments available for a category
     *
     * @param <type> $category_id
     */
    public function getList($category_id, $limit = false) {
        if (is_array($category_id)) {
            $categoryIDList = implode(',', $category_id);

            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id IN ($categoryIDList)
                      AND availability = 'survey'
                      AND deleted_at IS NULL
                      ORDER BY finish";
        }
        else {
            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id = '$category_id'
                      AND availability = 'survey'
                      AND deleted_at IS NULL
                      ORDER BY finish";
        }

		if (is_numeric($limit)) {
			$query .= " LIMIT $limit";
		}

        return $this->db->getAll($query);
    }
}
?>
