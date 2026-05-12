<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\WorkType\Helper as WorkTypeHelper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Permission\Access\Access;

/**
 * Feed of all events occurring on a user's homepages.
 *
 * @deprecated use `\Schoolbox\Feed\StandardFeed`
 */
class Feed
{
    /**
     * Constructor
     *
     * @deprecated use `\Schoolbox\Feed\StandardFeed`
     *
     * @param IConnector     $db   A database connection
     * @param Schoolbox\User $user The user to generate the feed for
     * @param AssessmentDataSource|null $assessmentDataSource Data source for assessment hydration
     */
    public function __construct(
        private IConnector $db,
        private Schoolbox\User $user,
        private ?AssessmentDataSource $assessmentDataSource = null
    ) {
    }

    /**
     * @deprecated use `\Schoolbox\Feed\StandardFeed`
     * @param int $folderId
     * @param int $limit
     * @return array
     */
    public function getFeed($folderId, $limit)
    {
        if ($limit == 0 || $limit > 50) {
            $limit = 50;
        }

        /* ALL THESE HELPER FUNCTIONS TO BE SYSTEMATICALLY DEPRECATED */

        $files             = $this->getFiles($folderId, $limit);
        $links             = $this->getLinks($folderId, $limit);
        $folders           = $this->getFolders($folderId, $limit);
        $wiki              = $this->getWiki($folderId, $limit);
        $forum             = $this->getForum($folderId, $limit);
        $blog              = $this->getBlog($folderId, $limit);
        $blogComments      = $this->getBlogComments($folderId, $limit);
        $onlineAssessments = $this->getOnlineAssessments($folderId, $limit);
        $surveys           = $this->getSurveys($folderId, $limit);

        is_array($files) ? 0 : $files = [];
        is_array($links) ? 0 : $links = [];
        is_array($folders) ? 0 : $folders = [];
        is_array($wiki) ? 0 : $wiki = [];
        is_array($forum) ? 0 : $forum = [];
        is_array($blog) ? 0 : $blog = [];
        is_array($blogComments) ? 0 : $blogComments = [];
        is_array($onlineAssessments) ? 0 : $onlineAssessments = [];
        is_array($surveys) ? 0 : $surveys = [];

        //get each of the areas
        $feedList = array_merge(
            $files,
            $links,
            $folders,
            $wiki,
            $forum,
            $blog,
            $blogComments,
            $onlineAssessments,
            $surveys
        );

        //sort them
        usort($feedList, [$this, "timecmp"]);

        //cut off over limit
        $feedList = array_slice($feedList, 0, $limit);

        return $feedList;
    }

    /**
     * Generic filter function for get* methods.
     *
     * @param string|array $folderId Either a number string, or an array of IDs
     * @return string
     */
    protected function getFilterFromCategoryId($folderId)
    {
        if (is_array($folderId) && !empty($folderId)) {
            return ' IN (' . implode(',', array_map('intval', $folderId)) . ') ';
        } else {
            return " = " . intval($folderId);
        }
    }

    public function getFiles($folderId, $limit)
    {
        $file = [];
        if (empty($folderId)) {
            return [];
        }

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);
        $query = $this->db->prepare(
            "
                SELECT
                    f.id,
                    u.firstname,
                    u.preferred_name,
                    u.lastname,
                    u.title,
                    u.id as uid,
                    synergy_id as external_id,
                    u.deleted_at as 'u.deleted_at',
                    UNIX_TIMESTAMP(f.submitted) submitted,
                    f.description as name,
                    realname,
                    af.description as type,
                    c.name as cat_name,
                    c.id as cat_id
                FROM file f
                LEFT JOIN storage s on f.hash = s.hash
                LEFT JOIN allowedFileExtension afe ON s.ext = afe.extension
                LEFT JOIN allowedFileTypes af ON afe.extension = af.fileTypeID
                LEFT JOIN user u ON u.id = f.owner
                LEFT JOIN folder c ON c.id = f.category
                WHERE f.category IN {$where} AND f.hidden = 0 AND f.deleted_at IS NULL
                ORDER BY f.submitted DESC LIMIT 0, :limit
            "
        );
        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $link = null;
            if ($row['realname'] != '') {
                $url  = '<a href="/send.php?id=' . $row['id'] . '" rel="external">' . Scrub::text($row['name']) . '</a>';
                $link = '/send.php?id=' . $row['id'];
            } else {
                $url = Scrub::text($row['name']);
            }

            $file[] = [
                'date'        => $row['submitted'],
                'type'        => 'icon-files',
                'message'     => $userHandler->formatName($row) . ' uploaded the file ' . Scrub::text($row['name']),
                'messageHTML' => "{$this->buildUsernameHtml($row, $row['uid'], $userHandler)} uploaded the file  $url",
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],

                'user_id' => $row['uid'],
                'link'    => $link,
                'name'    => $row['name'],
                'action'  => 'uploaded the file',
            ];
        }

        return $file;
    }

    public function getLinks($folderId, $limit)
    {
        $link = [];

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);
        $query = $this->db->prepare("
            SELECT
                l.id,
                u.firstname,
                u.preferred_name,
                u.lastname,
                u.title,
                u.id as uid,
                synergy_id as external_id,
                u.deleted_at as 'u.deleted_at',
                UNIX_TIMESTAMP(l.submitted) submitted,
                description as name,
                url,
                c.name as cat_name,
                c.id as cat_id
            FROM link l
            LEFT JOIN user u ON u.id = l.owner
            LEFT JOIN folder c ON c.id = l.category
            WHERE category IN {$where} AND l.hidden = 0
            ORDER BY l.submitted DESC LIMIT 0, :limit
        ");
        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $url = new Url($row['url']);
            if ($url->isLocalUrl()) {
                $linkUrl    = $url->getRelativeURL();
                $linkTarget = '';
            } else {
                $linkUrl    = $url->getBase64EncodedRedirect();
                $linkTarget = 'external';
            }

            $link[] = [
                'date'        => $row['submitted'],
                'type'        => 'icon-link',
                'message'     => $userHandler->formatName($row) . ' submitted the link ' . Scrub::text($row['name']),
                'messageHTML' => $this->buildUsernameHtml($row, $row['uid'], $userHandler) . ' submitted the link <a href="' . $linkUrl . '" rel="' . $linkTarget . '">' . Scrub::text(
                    $row['name']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],

                'user_id' => $row['uid'],
                'link'    => $linkUrl,
                'name'    => $row['name'],
                'action'  => 'submitted the link',
            ];
        }
        return $link;
    }

    /**
     * Get a list of online assessments (aka 'quizzes') that were created.
     *
     * @param string|array $folderIds
     * @param int          $limit
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getOnlineAssessments($folderIds, int $limit): array
    {
        [$where, $params] = $this->buildPlaceholdersAndParams($folderIds);

        $access = Access::getInstance();
        $onlineAssessments = [];
        $offset = 0;

        // Fetch in batches to accumulate exactly $limit visible rows, since PHP-side visibility
        // filtering may exclude some SQL rows and a single LIMIT query can under-return.
        $batchSize = $limit;

        $query = "
            SELECT
                a.id AS assessment_id,
                assess.assess_id, assess.title AS heading, UNIX_TIMESTAMP(assess.start) as start, assess.category_id as folderId,
                u.title, u.firstname, u.preferred_name, u.lastname, u.id AS uid, synergy_id as external_id, u.deleted_at as 'u.deleted_at',
                c.name as cat_name, c.id as cat_id,
                lwt.is_visible_to_parents, lwt.is_visible_to_learners,
                a.work_type_id
            FROM assess
            JOIN assessment a on assess.assessment_id = a.id
            LEFT JOIN lu_work_type lwt ON lwt.id = a.work_type_id
            LEFT JOIN user u ON assess.user_id = u.id
            LEFT JOIN folder c ON c.id = assess.category_id
            WHERE assess.category_id IN {$where}
            AND assess.start < NOW()
            AND assess.deleted_at IS NULL
            AND assess.availability = 'assess'
            ORDER BY assess.created_at DESC LIMIT :offset, :batchSize
        ";

        while (count($onlineAssessments) < $limit) {
            $params['offset'] = $offset;
            $params['batchSize'] = $batchSize;
            $rows = $this->db->executeQuery($query, $params)->fetchAll();

            if (empty($rows)) {
                break;
            }

            $assessmentsById = $this->loadAssessmentsByIdWithParticipationForVisibilityChecks($rows);

            foreach ($rows as $row) {
                if ($row['work_type_id'] !== null) {
                    $isVisibleToParents = self::isDbBooleanEnabled($row['is_visible_to_parents']);
                    $isVisibleToLearners = self::isDbBooleanEnabled($row['is_visible_to_learners']);

                    // Filter out rows that should not be visible due to lwt.is_visible_to_parents/lwt.is_visible_to_learners.
                    // When work_type_id is null, no visibility restriction applies (consistent with Assessment\Visibility->isVisibleToUser()).
                    // Note assessment publish/close dates are not relevant here, because we are just getting all the
                    // assessments that have been created (according to this function's main docblock).
                    $isInstructor = $access->isInstructorInFolderId($this->user, $row['folderId']);
                    $isLearnerParticipant = false;
                    $isParentOfLearnerParticipant = false;

                    if (!$isInstructor) {
                        $assessment = $assessmentsById[(int)$row['assessment_id']] ?? null;
                        if ($assessment === null) {
                            continue; // If this happens, the database has changed between queries. Skip the row.
                        }

                        $isLearnerParticipant = $this->isLearnerParticipant(
                            $access,
                            $assessment,
                            (int)$row['folderId'],
                            $this->user
                        );

                        if ($isVisibleToParents) {
                            $isParentOfLearnerParticipant = $this->isParentOfLearnerParticipant(
                                $access,
                                $assessment,
                                (int)$row['folderId'],
                                $this->user
                            );
                        }
                    }

                    switch (
                        WorkTypeHelper::getVisibilityFromBooleans(
                            $isVisibleToParents,
                            $isVisibleToLearners
                        )
                    ) {
                        case WorkTypeMapper::VISIBILITY_STAFF_INSTRUCTORS:
                            // Staff and instructors only (conditions here copied from Assessment\WorkType\Mapper)
                            //  - Instructors - always included
                            //  - Learner participants - always excluded
                            //  - Parents of learner participants - included only if instructors
                            //  - Users of role type 'staff' - included unless they are learner participants
                            //  - Other users of any role type - included only if instructors
                            if (!$isInstructor) {
                                if ($isLearnerParticipant) {
                                    continue 2; // Skip row.
                                }
                                if (!$this->user->getRole()->isStaff()) {
                                    continue 2; // Skip row.
                                }
                            }
                            break;
                        case WorkTypeMapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS:
                            // Staff and instructors and learners (conditions here copied from Assessment\WorkType\Mapper)
                            //  - Instructors - always included
                            //  - Learner participants - always included
                            //  - Parents of learner participants - included only if instructors or learner participants
                            //  - Users of role type 'staff' - always included
                            //  - Other users of any role type - included only if instructors or learner participants
                            if (
                                !$isInstructor &&
                                !$isLearnerParticipant &&
                                !$this->user->getRole()->isStaff()
                            ) {
                                continue 2; // Skip row.
                            }
                            break;
                        case WorkTypeMapper::VISIBILITY_STAFF_INSTRUCTORS_PARENTS:
                            // Staff and instructors and parents (conditions here copied from Assessment\WorkType\Mapper)
                            //  - Instructors - always included
                            //  - Learner participants - always excluded
                            //  - Parents of learner participants - included unless they are learner participants
                            //  - Users of role type 'staff' - included unless they are learner participants
                            //  - Other users of any role type - included only if instructors
                            if (!$isInstructor) {
                                if ($isLearnerParticipant) {
                                    continue 2; // Skip row.
                                }
                                if (
                                    !$isParentOfLearnerParticipant &&
                                    !$this->user->getRole()->isStaff()
                                ) {
                                    continue 2; // Skip row.
                                }
                            }
                            break;
                        case WorkTypeMapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS_PARENTS:
                            // Staff and instructors and learners and parents (conditions here copied from Assessment\WorkType\Mapper)
                            //  - Instructors - always included
                            //  - Learner participants - always included
                            //  - Parents of learner participants - always included
                            //  - Users of role type 'staff' - always included
                            //  - Other users of any role type - included only if instructors or learner participants
                            if (
                                !$isInstructor &&
                                !$isLearnerParticipant &&
                                !$isParentOfLearnerParticipant &&
                                !$this->user->getRole()->isStaff()
                            ) {
                                continue 2; // Skip row.
                            }
                            break;
                        default:
                            throw new LogicException('Impossible case.');
                    }
                }

                $formattedUserName = $this->formatOnlineAssessmentUserName($row);
                $onlineAssessments[] = [
                    'date'        => $row['start'],
                    'type'        => 'icon-quiz',
                    'message'     => (
                        $formattedUserName
                        . ' opened the quiz '
                        . Scrub::text($row['heading'])
                    ),
                    'messageHTML' => (
                        $this->buildUsernameHtmlFromName($row, $row['uid'], $formattedUserName) .
                        ' opened the quiz <a href="/assess/intro/' . $row['assess_id'] . '">'
                        . Scrub::text($row['heading']) . '</a>'
                    ),
                    'external_id' => $row['external_id'],
                    'user_name'   => $formattedUserName,
                    'cat_name'    => $row['cat_name'],
                    'cat_id'      => $row['cat_id'],
                    'user_id'     => $row['uid'],
                    'link'        => '/assess/intro/' . $row['assess_id'],
                    'name'        => $row['heading'],
                    'action'      => 'opened the quiz',
                ];

                if (count($onlineAssessments) >= $limit) {
                    break 2; // Reached the limit; stop fetching.
                }
            }

            if (count($rows) < $batchSize) {
                break; // Fetched fewer rows than requested, so the result set is exhausted.
            }

            $offset += $batchSize;
        }

        return $onlineAssessments;
    }

    /**
     * Get a list of surveys that were created.
     *
     * @param string|array $folderId
     * @param int $limit
     * @return array
     */
    public function getSurveys($folderId, $limit)
    {
        $surveys = [];

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);

        $query = $this->db->prepare("
            SELECT
                assess.assess_id, assess.title AS heading, UNIX_TIMESTAMP(assess.start) as start,
                u.title, u.firstname, u.preferred_name, u.lastname, u.id AS uid, synergy_id as external_id, u.deleted_at as 'u.deleted_at',
                c.name as cat_name, c.id as cat_id
            FROM assess
            LEFT JOIN user u ON assess.user_id = u.id
            LEFT JOIN folder c ON c.id = assess.category_id
            WHERE assess.category_id IN {$where}
            AND assess.start < NOW()
            AND assess.deleted_at IS NULL
            AND assess.availability = 'survey'
            ORDER BY assess.created_at DESC LIMIT 0, :limit
        ");

        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $surveys[] = [
                'date'        => $row['start'],
                'type'        => 'icon-surveys',
                'message'     => $userHandler->formatName($row)
                    . ' opened the survey '
                    . Scrub::text($row['heading']),
                'messageHTML' => $this->buildUsernameHtml($row, $row['uid'], $userHandler) . ' opened the survey <a href="/survey/intro/' . $row['assess_id'] . '">'
                    . Scrub::text($row['heading']) . '</a>',
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],
                'user_id'     => $row['uid'],
                'link'        => '/survey/intro/' . $row['assess_id'],
                'name'        => $row['heading'],
                'action'      => 'opened the survey',
            ];
        }

        return $surveys;
    }

    public function getFolders($folderId, $limit)
    {
        $folder = [];

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);
        $query = $this->db->prepare("
            SELECT c.id, c.name, UNIX_TIMESTAMP(time) submitted, action, u.firstname, u.preferred_name, u.lastname, u.title, u.id as uid, synergy_id as external_id, u.deleted_at as 'u.deleted_at', pc.name as cat_name, pc.id as cat_id
            FROM cmsLog
            JOIN folder c ON objectId = c.id
            JOIN user u ON user = u.id
            JOIN folder pc ON pc.id = c.parent
            WHERE c.parent IN {$where} AND object = 'folder' AND action = 'create'
            ORDER BY time DESC LIMIT 0, :limit
        ");

        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $folder[] = [
                'date'        => $row['submitted'],
                'type'        => 'icon-folder',
                'message'     => $userHandler->formatName($row) . ' created the folder ' . Scrub::text($row['name']),
                'messageHTML' => $this->buildUsernameHtml($row, $row['uid'], $userHandler) . ' created the folder <a href="/homepage/' . $row['id'] . '">' . Scrub::text(
                    $row['name']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],
                'user_id'     => $row['uid'],
                'link'        => '/homepage/' . $row['id'],
                'name'        => $row['name'],
                'action'      => 'created the folder',
            ];
        }

        return $folder;
    }

    public function getForum($folderId, $limit)
    {
        $forum = [];

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);
        $query = $this->db->prepare("
            SELECT f.threadID, f.title as heading,  UNIX_TIMESTAMP(`date`) submitted, u.firstname, u.preferred_name, u.lastname, u.title, u.id as uid, synergy_id as external_id, u.deleted_at as 'u.deleted_at', c.name as cat_name, c.id as cat_id
            FROM forumThread f
            JOIN user u ON u.id = f.userID
            LEFT JOIN folder c ON c.id = f.categoryID
            WHERE f.deleted_at IS NULL AND f.categoryID IN {$where}
            ORDER BY `date` DESC LIMIT 0, :limit
        ");

        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $forum[] = [
                'date'        => $row['submitted'],
                'type'        => 'icon-forum',
                'message'     => $userHandler->formatName($row) . ' started the thread ' . Scrub::text($row['heading']),
                'messageHTML' => $this->buildUsernameHtml($row, $row['uid'], $userHandler) . ' started the thread <a href="/forum/thread/' . $row['threadID'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],
                'user_id'     => $row['uid'],
                'link'        => '/forum/thread/' . $row['threadID'],
                'name'        => $row['heading'],
                'action'      => 'started the thread',
            ];
        }

        return $forum;
    }


    /**
     * @deprecated use \Schoolbox\Wiki\WikiNotifier::getFeed()
     * @param string|array $folderId
     * @param int $limit
     * @return array
     */
    public function getWiki($folderId, $limit)
    {
        $wiki = [];

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);

        $query = $this->db->prepare("
            SELECT w.wikiID, heading, w.categoryID, w.slug, UNIX_TIMESTAMP(r.created) submitted, u.firstname, u.preferred_name, u.lastname, u.title, u.id as uid, synergy_id as external_id, u.deleted_at as 'u.deleted_at', c.name as cat_name, c.id as cat_id
            FROM wikiRevision r
            JOIN wiki w ON w.wikiID = r.wikiID
            JOIN user u ON u.id = r.userID
            LEFT JOIN folder c ON c.id = w.categoryID
            WHERE w.deleted_at IS NULL AND w.categoryID IN {$where}
            ORDER BY r.created DESC LIMIT 0, :limit
        ");

        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $wiki[] = [
                'date'        => $row['submitted'],
                'type'        => 'icon-wiki',
                'message'     => $userHandler->formatName($row) . ' updated the wiki ' . Scrub::text($row['heading']),
                'messageHTML' => $this->buildUsernameHtml($row, $row['uid'], $userHandler) . ' updated the wiki <a href="/wiki/' . $row['categoryID'] . '/' . $row['slug'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],
                'user_id'     => $row['uid'],
                'link'        => '/wiki/' . $row['categoryID'] . '/' . $row['slug'],
                'name'        => $row['heading'],
                'action'      => 'updated the wiki',
            ];
        }

        return $wiki;
    }

    /**
     * Get a list of new blog entries to push into the feed.
     *
     * @deprecated use \Schoolbox\Blog\BlogNotifier::getFeed()
     * @param  string|array$folderId
     * @param  int $limit
     * @return array
     */
    public function getBlog($folderId, $limit)
    {
        $blog = [];

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);
        $query = $this->db->prepare("
            SELECT u.id AS uid, u.title, u.firstname, u.preferred_name, u.lastname, synergy_id as external_id, u.deleted_at as 'u.deleted_at', b.id AS blogID, heading, UNIX_TIMESTAMP(b.created_at) AS submitted, c.name as cat_name, c.id as cat_id
            FROM user u
            JOIN blog b
            ON u.id = b.owner_id
            LEFT JOIN folder c ON c.id = folder_id
            WHERE folder_id IN {$where}
            AND b.deleted_at IS NULL
            ORDER BY submitted LIMIT 0, :limit
        ");

        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $blog[] = [
                'date'        => $row['submitted'],
                'type'        => 'icon-blog',
                'message'     => $userHandler->formatName($row) . ' updated the blog ' . Scrub::text($row['heading']),
                'messageHTML' => $this->buildUsernameHtml($row, $row['uid'], $userHandler) . ' updated the blog <a href="/blog/' . $row['blogID'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],
                'user_id'     => $row['uid'],
                'link'        => '/blog/' . $row['blogID'],
                'name'        => $row['heading'],
                'action'      => 'updated the blog',
            ];
        }

        return $blog;
    }

    /**
     * Get a list of new blog comments to push into the feed.
     *
     * @deprecated use \Schoolbox\Blog\CommentNotifier::getFeed()
     * @param  string|array $folderId
     * @param  int $limit
     * @return array
     */
    public function getBlogComments($folderId, $limit)
    {
        $blogComments = [];

        [$where, $params] = $this->buildPlaceholdersAndParams($folderId);
        $query = $this->db->prepare("
            SELECT u.id AS uid, u.title, u.firstname, u.preferred_name, u.lastname, synergy_id as external_id, u.deleted_at as 'u.deleted_at',
            UNIX_TIMESTAMP(bc.created_at) AS submitted, b.id AS blogID, heading, folder_id, c.name as cat_name, c.id as cat_id
            FROM `user` u
            JOIN blog_comment bc ON u.id = bc.owner_id
            JOIN blog b ON bc.blog_id = b.id
            LEFT JOIN folder c ON c.id = folder_id
            WHERE folder_id IN {$where}
            AND b.deleted_at IS NULL
            ORDER BY submitted LIMIT 0, :limit
        ");

        $this->executeWithLimit($query, $params, $limit);

        $userHandler = new User();

        foreach ($query as $row) {
            $blogComments[] = [
                'date'        => $row['submitted'],
                'type'        => 'icon-blog',
                'message'     => $userHandler->formatName($row) . ' updated the blog ' . Scrub::text($row['heading']),
                'messageHTML' => $this->buildUsernameHtml($row, $row['uid'], $userHandler) . ' commented on the blog <a href="/blog/' . $row['blogID'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name'   => $userHandler->formatName($row),
                'cat_name'    => $row['cat_name'],
                'cat_id'      => $row['cat_id'],
                'user_id'     => $row['uid'],
                'link'        => '/blog/' . $row['blogID'],
                'name'        => $row['heading'],
                'action'      => 'commented on the blog',
            ];
        }

        return $blogComments;
    }

    // TODO: Group member changes

    // TODO: Homepage layout changes

    // TODO: Submission boxes

    // TODO: blog

    // TODO: text box updates

    private function timecmp($a, $b)
    {
        //just a quick function to enable sorting of news based on time
        if ($a['date'] == $b['date']) {
            return 0;
        }
        return ($a['date'] > $b['date']) ? -1 : 1;
    }

    /**
     * Helper to consistently build a HTML of a user's name, with link if user not deleted
     *
     * @param array $row
     * @param int   $userId
     * @param User  $userHandler
     * @return string
     */
    private function buildUsernameHtml($row, $userId, User $userHandler)
    {
        return null === $row['u.deleted_at']
            ? sprintf('<a href="/search/user/%s">%s</a>', $userId, Scrub::text($userHandler->formatName($row)))
            : Scrub::text($userHandler->formatName($row));
    }

    /**
     * Builds linked username HTML from a preformatted display name.
     *
     * @param array  $row
     * @param int    $userId
     * @param string $formattedName
     * @return string
     */
    private function buildUsernameHtmlFromName(array $row, int $userId, string $formattedName): string
    {
        return null === $row['u.deleted_at']
            ? sprintf('<a href="/search/user/%s">%s</a>', $userId, Scrub::text($formattedName))
            : Scrub::text($formattedName);
    }

    /**
     * Formats the username for an online assessment feed item.
     *
     * @param array<string, mixed> $row
     * @return string
     */
    protected function formatOnlineAssessmentUserName(array $row): string
    {
        return new User()->formatName($row);
    }

    /**
     * Returns whether an un-hydrated numeric database boolean is enabled.
     *
     * @param mixed $value
     * @return bool
     */
    private static function isDbBooleanEnabled(mixed $value): bool
    {
        return (int)$value === 1;
    }

    /**
     * Hydrates the assessments referenced by these feed rows, loads the participation needed for
     * visibility checks, and returns them keyed by assessment ID.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, \Schoolbox\Learning\Assessment\IAssessable>
     */
    private function loadAssessmentsByIdWithParticipationForVisibilityChecks(array $rows): array
    {
        $assessmentIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int)$row['assessment_id'],
            array_filter(
                $rows,
                static fn (array $row): bool => $row['work_type_id'] !== null
            )
        )));

        if ($assessmentIds === []) {
            return [];
        }

        if (!$this->assessmentDataSource) {
            throw new LogicException('Assessment data source not configured.');
        }

        $assessments = $this->assessmentDataSource->findAllByIds($assessmentIds);

        // The intent here is to hydrate only the participation records necessary to enable calls to
        // this->isLearnerPartipant() and this->isParentOfLearnerParticipant().  For those calls to work, we only need
        // participation records for the logged-in user and his/her children.  The loadParticipationIntoAssessmentCollection()
        // function gets participation records for either one user or all users, so for the case where the logged-in
        // user has children (where we set $participantUser as null below), we are overhydrating here.
        $participantUser = $this->shouldLoadAllParticipantsForRows($rows) ? null : $this->user;
        $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection($assessments, $participantUser, true);
        return $assessments->indexBy('id')->getAll();
    }

    /**
     * Returns whether the supplied user is both a learner in the folder and a participant in the assessment.
     *
     * @param Access          $access
     * @param IAssessable     $assessment
     * @param int             $folderId
     * @param \Schoolbox\User $user
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    private function isLearnerParticipant(
        Access $access,
        IAssessable $assessment,
        int $folderId,
        Schoolbox\User $user
    ): bool {
        return $access->isLearnerInFolderId($user, $folderId)
            && $assessment->getParticipantIfExtant($user) !== null;
    }

    /**
     * Returns whether the supplied user has at least one child who is
     * both a learner in the folder and a participant in the assessment.
     *
     * @param Access          $access
     * @param IAssessable     $assessment
     * @param int             $folderId
     * @param \Schoolbox\User $user
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    private function isParentOfLearnerParticipant(
        Access $access,
        IAssessable $assessment,
        int $folderId,
        Schoolbox\User $user
    ): bool {
        $children = $user->getChildren();

        if ($children === null) {
            return false;
        }

        foreach ($children as $child) {
            if (
                $access->isLearnerInFolderId($child, $folderId) &&
                $assessment->getParticipantIfExtant($child) !== null
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parent visibility checks need child participation, so the full batch must be loaded when
     * the current user has children and any row in the batch is parent-visible.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function shouldLoadAllParticipantsForRows(array $rows): bool
    {
        $children = $this->user->getChildren();

        if ($children === null || $children->count() === 0) {
            return false;
        }

        return array_any(
            $rows,
            fn ($row) => $row['work_type_id'] !== null && self::isDbBooleanEnabled($row['is_visible_to_parents'])
        );
    }

    /**
     * Builds a set of placeholders and params for use in one of this class'
     * queries.
     *
     * @param array $folderIds
     * @return array{string, array<string, int>}
     */
    private function buildPlaceholdersAndParams(array $folderIds): array
    {
        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folderId');
        $params       = Builder::getArrayParameters($folderIds, 'folderId');
        return [$placeholders, $params];
    }

    /**
     * Executes `$query` with `$params` and `$limit`.
     *
     * Assumes `$query` has a placeholder `:limit`.
     *
     * @param PDOStatement $query
     * @param array        $params
     * @param int          $limit
     * @return void
     */
    private function executeWithLimit(PDOStatement $query, array $params, int $limit): void
    {
        foreach ($params as $param => $value) {
            $query->bindValue($param, $value);
        }
        $query->bindValue('limit', $limit, PDO::PARAM_INT);
        $query->execute();
    }
}
