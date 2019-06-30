<?php

/* Due Work: Recent work. Should be a multiple of 3 because there are three
* types: Due, Awaiting and Marked.
*/
use Schoolbox\Collection;

define('UPCOMING_WORK_TYPE', 1);
define('AWAITING_WORK_TYPE', 2);
define('RETURNED_WORK_TYPE', 3);
define('OVERDUE_WORK_TYPE', 4);
define('RECENT_WORK_LIMIT', 12);

/**
 * Class Submission
 * @deprecated use Schoolbox\Assessment\SubmissionBoxMapper
 */
class Submission extends DeprecatedModel implements \Schoolbox\IHomepageCleanable
{

    /**
     * Submission constructor.
     * @deprecated
     */
    function __construct()
    {

        $this->_table = "submission_box";
        $this->_index = "id";

        parent::__construct();
    }

    /**
     * @deprecated
     * @param $slug
     * @return array|bool|null
     */
    function getMarkTypeBySlug($slug)
    {
        $query = "SELECT *
                  FROM submission_mark_type
                  WHERE slug = '$slug'";

        return $this->db->getRow($query);
    }
}
