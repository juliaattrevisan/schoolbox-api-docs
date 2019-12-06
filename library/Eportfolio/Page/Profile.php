<?php
/**
 * Eportfolio_Page_Profile
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Resource\Folder\Model as Folder;

class Eportfolio_Page_Profile extends Eportfolio_Page_Abstract
{
    /**
     * The current user (used for authenticating whether a user can view something)
     *
     * @var \Schoolbox\User
     */
    protected $currentUser;

    /**
     * Number of Recent Showcased Items to display
     *
     * @var int
     */
    protected $numShowcasedItems = -1;

    /**
     * Number of Recent Blogs to display
     *
     * @var int
     */
    protected $numRecentBlogs = -1;

    /**
     * Number of recent photos to display
     *
     * @var int
     */
    protected $numRecentPhotos = -1;

    /**
     * The template file to use
     *
     * @var string
     */
    protected $templateFile = 'eportfolio/profile.html';

    /**
     * Initialise classes and collections
     */
    protected function initialise()
    {
        $this->setNumShowcasedItems(3);
        $this->setNumRecentBlogs(3);
        $this->setNumRecentPhotos(3);
        $this->setCurrentUser($_SESSION['user']);
    }

    /**
     * Set up blocks and stuff
     */
    protected function setup()
    {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setTab('ePortfolio');

        $this->getPage()->setBlock(TEMPLATE_content, 'has_subjects', 'has_subjects_block');
        $this->getPage()->setBlock('has_subjects', 'subject', 'subject_block');
        $this->getPage()->setBlock('subject', 'subject_linked', 'subject_linked_block');
        $this->getPage()->setBlock('subject', 'subject_unlinked', 'subject_unlinked_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'has_groups', 'has_groups_block');
        $this->getPage()->setBlock('has_groups', 'group', 'group_block');
        $this->getPage()->setBlock('group', 'group_linked', 'group_linked_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_showcased_work', 'no_showcased_work_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'has_showcased_work', 'has_showcased_work_block');
        $this->getPage()->setBlock('has_showcased_work', 'showcased_work', 'showcased_work_block');
        $this->getPage()->setBlock('has_showcased_work', 'edit_showcase_work','edit_showcase_work_block');
        $this->getPage()->setBlock('showcased_work', 'submission_file_list', 'submission_file_list_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_recent_blog', 'no_recent_blog_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'has_recent_blog', 'has_recent_blog_block');
        $this->getPage()->setBlock('has_recent_blog', 'recent_blog', 'recent_blog_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_recent_photo', 'no_recent_photo_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'has_recent_photo', 'has_recent_photo_block');
        $this->getPage()->setBlock('has_recent_photo', 'recent_photo', 'recent_photo_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'has_cover_photo', 'has_cover_photo_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_cover_photo', 'no_cover_photo_block');
    }

    /**
     * Render page
     */
    protected function render()
    {
        $this->renderPersonal();

        $this->renderCoverPhoto();
        $this->renderShowcasedWork();
        $this->renderRecentPhotos();
        $this->renderRecentBlogs();

        $this->renderSubjects();
        $this->renderGroups();
    }

    /**
     * Render personal details
     */
    private function renderPersonal()
    {
        $house = $this->getUserHouse();

        $this->getPage()->setVar(array(
            'USER_EXTERNAL_ID' => $this->getExternalUserId(),
            'USER_FULLNAME' => $this->getUserName(),
            'USER_CAMPUS' => Scrub::text(' '.$this->getUserCampusName()),
            'USER_YEAR_LEVEL' => $this->getUserYear() != '' ? Scrub::text('Year '.$this->getUserYear()) : '',
            'USER_HOUSE' => $house != '' ? Scrub::text(', '.$this->getUserHouse()) : '' ,
        ));
    }

    /**
     * Render cover phto
     */
    private function renderCoverPhoto()
    {
        $coverPhotoMapper = new \Eportfolio\CoverPhotoMapper();

        $coverPhotoCollection = $coverPhotoMapper->findLatest($this->getUserId());

        if ($coverPhotoCollection->count()) {
            $this->getPage()->setVar(array(
                'COVERPHOTO_IMAGEHASH' => $coverPhotoCollection->first()->getImagehash(),
            ));

            $this->getPage()->parse('has_cover_photo_block', 'has_cover_photo');
            $this->getPage()->setVar('no_cover_photo_block', '');
        } else {
            $this->getPage()->parse('no_cover_photo_block', 'no_cover_photo');
            $this->getPage()->setVar('has_cover_photo_block', '');
        }

        /* Control button
         */
        $this->getPage()->setVar('ATTACH_ZONE', '');
        if ($this->getCurrentUser()->getId() == $this->getUserId()) {
            $zone = new \Schoolbox\Resource\Attachment\Zone('cover-photo');
            $zone
                ->setButtonMode(true)
                ->setMulti(false)
                ->setMimeFilter('image/*')
                ->setThumbnailSize(32)
                ->setAttachPrompt('upload a cover photo')
                ->setModalTitle('Select a cover photo')
                ->setAttachmentsLabel('');
            $zone->renderIntoSitePage($this->getPage(), 'ATTACH_ZONE');
        }
    }

    /**
     * Render recent showcased work
     * @todo For now, just to get this working for 165, we are rendering the first file in StudentSubmissions and
     * ReturnedWork. But since 165+ can have multiple files per Submission or Return, this templates needs updating.
     * @see SA-1606
     */
    private function renderShowcasedWork()
    {
        $showcaseMapper = new \Eportfolio\ShowcaseMapper();

        /* Gather around the loop, everyone
         */
        $allShowcasedItems = $showcaseMapper->getRecentShowcasedSubmissionBoxes($this->getUser(), $this->getNumShowcasedItems());

        if ($allShowcasedItems->count()) {
            $previousCategory = null;

            $currentUser = $this->getCurrentUser();
            foreach ($allShowcasedItems as $currentShowcasedItem) {
                $currentBox = $currentShowcasedItem->getSubmissionBox();

                if (!($currentBox instanceof Schoolbox\Assessment\Component\SubmissionBox)) {
                    continue;
                }

                $latestSubmission = $this->getLatestSubmission($this->getUser(), $currentBox);
                $latestReturn = $this->getLatestReturn($this->getUser(), $currentBox);
                $workType = $currentBox->getAssessmentModel()->getWorkType();

                $this->getPage()->setVar(array(
                    'SHOWCASED_WORK_ITEM_ID' => $currentShowcasedItem->getId(),
                    'SHOWCASED_WORK_BOX_CATEGORY_NAME' => $currentBox->getCategory()->getName(),
                    'SHOWCASED_WORK_BOX_CATEGORY_ID' => $currentBox->getCategory()->getId(),
                    'SHOWCASED_WORK_BOX_NAME' => $currentBox->getName(),
                    'SHOWCASED_WORK_ASSESSMENT_ID' => $currentBox->getAssessmentId(),
                    'SHOWCASED_WORK_BOX_TYPE' => $workType ? $workType->getName() : '',
                ));

                /* Show submitted work information (if available)
                 */
                if ($latestSubmission) {
                    foreach($latestSubmission->getFiles() as $currentFile) {
                        $this->getPage()->setVar([
                            'SHOWCASED_WORK_SUBMISSION_ID'        => $currentFile->getId(),
                            'SHOWCASED_WORK_FILE_NAME'            => $currentFile->getFilename(),
                            'SHOWCASED_WORK_FILE_HASH'            => $currentFile->getHash(),
                            'SHOWCASED_WORK_SUBMISSION_FILESIZE'  => Alaress_Byte_Format::bytesToString($currentFile->getSize()),
                            'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => $currentFile->getCreatedAt()->getShort(),
                            'SHOWCASED_WORK_MIMETYPE_ICONFILE'    => $currentFile->getMimetype()->getIconFile(),
                        ]);

                        $this->getPage()->parse('submission_file_list_block', 'submission_file_list',true);
                    }
                } else {
                    $this->getPage()->setVar(array(
                        'SHOWCASED_WORK_SUBMISSION_FILESIZE' => '',
                        'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => '',
                    ));

                    $this->getPage()->setVar('has_submission_block', '');
                }

                /* Show returned work information (if available)
                 */
                if ($latestReturn && $currentBox->isMarkShownToRole($currentUser->getRole())) {
                    $mark = $latestReturn->getHumanReadableMark();
                    $this->getPage()->setVar(array(
                        'SHOWCASED_WORK_RETURNED_MARK' => $mark,
                        'SHOWCASED_WORK_RETURNED_GRADIENT' => Config::get('grade_gradient_enable') ? $latestReturn->getGradientFromNormalizedMark() : 'none',
                    ));
                } else {
                    $this->getPage()->setVar(array(
                        'SHOWCASED_WORK_RETURNED_MARK' => '',
                        'SHOWCASED_WORK_RETURNED_GRADIENT' => ''
                    ));
                }

                $this->getPage()->parse('showcased_work_block', 'showcased_work', true);

                /* Clear children after parsing out the parent
                 */
                $this->getPage()->setVar('submission_file_list_block','');
            }

            if($this->getUser()->getId() == $this->getCurrentUser()->getId()) {
                $this->getPage()->parse('edit_showcase_work_block', 'edit_showcase_work');
            }
            else {
                $this->getPage()->setVar('edit_showcase_work_block');
            }

            $this->getPage()->parse('has_showcased_work_block', 'has_showcased_work');
            $this->getPage()->setVar('no_showcased_work_block', '');
        } else {
            $this->getPage()->parse('no_showcased_work_block', 'no_showcased_work');
            $this->getPage()->setVar('has_showcased_work_block', '');
        }
    }

    /**
     * Render recent photos
     */
    private function renderRecentPhotos()
    {
        /* Gather everything
         */
        // TODO do this properly instead of by fetching amount*2 then filtering
        $allImages = \Component\Eportfolio\FileListMapper::getInstance()->findRecent(
            $this->getUserId(),
            $this->getNumRecentPhotos() * 2
        );
        $allImages = $allImages->filter(function($image) {
            /** @var \Component\Eportfolio\FileList $image */
            return $this->canUserViewPage($image->getPageId());
        });

        if ($allImages->count()) {
            /** @var \Component\Eportfolio\FileList[] $allImages */
            foreach ($allImages as $currentImage) {
                $this->getPage()->setVar(array(
                    'RECENT_PHOTO_FILE_ID'   => $currentImage->getFile()->getId(),
                    'RECENT_PHOTO_FILE_DESC' => Scrub::html($currentImage->getFile()->getTitle()),
                ));

                $this->getPage()->parse('recent_photo_block', 'recent_photo', true);
            }

            $this->getPage()->parse('has_recent_photo_block', 'has_recent_photo');
            $this->getPage()->setVar('no_recent_photo_block', '');
        } else {
            $this->getPage()->parse('no_recent_photo_block', 'no_recent_photo');
            $this->getPage()->setVar('has_recent_photo_block', '');
        }
    }

    /**
     * Render recent journal posts
     */
    private function renderRecentBlogs()
    {
        //  TODO do this properly instead of by fetching amount*2 then filtering
        $allBlogs = \Schoolbox\Blog\Eportfolio\Post\Mapper::getInstance()
            ->findForPortfolioOwner($this->getUser(),$this->getNumRecentBlogs() * 2);

        $allBlogs = $allBlogs->filter(function($blog) {
            /** @var Schoolbox\Blog\Eportfolio\Post\Model $blog */
            return $this->canUserViewPage($blog->getPage()->getPageId());
        });

        if ($allBlogs->count()) {

            /** @var Schoolbox\Blog\Eportfolio\Post\Model[] $allBlogs */
            foreach ($allBlogs as $currentBlog) {
                $this->getPage()->setVar(array(
                    'RECENT_BLOG_BLOG_ID' => $currentBlog->getId(),
                    'RECENT_BLOG_TITLE' => $currentBlog->getHeading(),
                    'RECENT_BLOG_CREATED_AT' => $currentBlog->getCreatedAt()->getRelativeTime(),
                    'RECENT_BLOG_PAGE_ID' => $currentBlog->getPage()->getPageId(),
                ));

                $this->getPage()->parse('recent_blog_block', 'recent_blog', true);
            }

            $this->getPage()->parse('has_recent_blog_block', 'has_recent_blog');
            $this->getPage()->setVar('no_recent_blog_block', '');
        } else {
            $this->getPage()->parse('no_recent_blog_block', 'no_recent_blog');
            $this->getPage()->setVar('has_recent_blog_block', '');
        }
    }

    /**
     * Can the current user view this page?
     *
     * @return bool
     */
    private function canUserViewPage($pageId)
    {
        $auth = new Eportfolio_Authenticator($this->getCurrentUser()->getId(), $this->getUserId(), $pageId);
        return $auth->canView();
    }

    /**
     * Render groups
     */
    private function renderGroups()
    {
        $groupHandler = new Group();
        $folderMapper = new FolderMapper();
        /** @var Schoolbox\Collection $allGroups */
        $allGroups = $folderMapper->findForUser($this->getUser(), false, UserAccess::ACCESS_READ, false, true);
        $allGroups->sortByCallback(function (Folder $f1, Folder $f2) {
            return strnatcasecmp($f1->getName(), $f2->getName());
        }, true);

        if ($allGroups->count() > 0) {
            foreach ($allGroups as $currentGroup) {

                /* Check permissions on whether you can view this subject's homepage
                 * If you can get there, you can see it, otherwise it's hidden
                 */
                if ($groupHandler->getPermission($currentGroup->getId()) >= UserAccess::ACCESS_READ) {
                    $this->getPage()->setVar(array(
                        'GROUP_ID' => $currentGroup->getId(),
                        'GROUP_NAME' => $currentGroup->getName(),
                    ));

                    $this->getPage()->parse('group_linked_block', 'group_linked');

                    /* Parse out group
                     */
                    $this->getPage()->parse('group_block', 'group', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->getPage()->setVar(array(
                        'group_linked_block' => '',
                        'group_unlinked_block' => ''
                    ));
                }
            }

            $this->getPage()->parse('has_groups_block', 'has_groups');
        } else {
            $this->getPage()->setVar('has_groups_block', '');
        }
    }

    /**
     * Render subjects
     */
    private function renderSubjects()
    {
        $groupHandler = new Group();

        if(!$this->getUser()->getHideTimetable()) {
            $allSubjects = $this->getGroupHandler()->involvedInSubjects($this->getUserId());
        }
        else {
            $allSubjects = false;
        }

        if (is_array($allSubjects) && count($allSubjects)) {
            foreach ($allSubjects as $currentSubject) {
                $this->getPage()->setVar(array(
                    'SUBJECT_ID' => $currentSubject['id'],
                    'SUBJECT_NAME' => $currentSubject['name'],
                ));

                /* Check permissions on whether you can view this subject's
                 * homepage - if not, don't link it
                 */
                if (($permission = $groupHandler->getPermission($currentSubject['id'])) < UserAccess::ACCESS_READ) {
                    $this->getPage()->parse('subject_unlinked_block', 'subject_unlinked');
                } else {
                    $this->getPage()->parse('subject_linked_block', 'subject_linked');
                }

                /* Parse out subject
                 */
                $this->getPage()->parse('subject_block', 'subject', true);

                /* Clear children after parsing out the parent
                 */
                $this->getPage()->setVar(array(
                    'subject_linked_block' => '',
                    'subject_unlinked_block' => ''
                ));
            }

            $this->getPage()->parse('has_subjects_block', 'has_subjects');
        } else {
            $this->getPage()->setVar('has_subjects_block', '');
        }
    }

    /* Getters/Setters
     */

    /**
     * Get a user's house name
     *
     * @return string
     */
    private function getUserHouse()
    {
        if($this->user->getSchoolHouse() instanceof House) {
            return $this->user->getSchoolHouse()->getHouse();
        }

        return '';
    }

    /**
     * Get a user's year level
     *
     * @return mixed
     */
    private function getUserYear()
    {
        return $this->user->getYear();
    }

    /**
     * Get a user's external id
     *
     * @return mixed
     */
    private function getExternalUserId()
    {
        return $this->user->getExternalId();
    }

    /**
     * Get a user's campus name
     *
     * @return mixed
     */
    private function getUserCampusName()
    {
        if ($this->user->getCampusCollection()->count()) {
            return implode(', ', $this->user->getCampusCollection()->getColumnValues('name'));
        }

        /* No campuses at all (for some reason)
         */
        return '';
    }

    /**
     * Set num showcased items
     *
     * @param int $numShowcasedItems
     */
    private function setNumShowcasedItems($numShowcasedItems)
    {
        $this->numShowcasedItems = $numShowcasedItems;
    }

    /**
     * Get num showcased items
     *
     * @return int
     */
    private function getNumShowcasedItems()
    {
        return $this->numShowcasedItems;
    }


    /**
     * @param int $numRecentBlogs
     */
    private function setNumRecentBlogs($numRecentBlogs)
    {
        $this->numRecentBlogs = $numRecentBlogs;
    }

    /**
     * @return int
     */
    private function getNumRecentBlogs()
    {
        return $this->numRecentBlogs;
    }

    /**
     * Set num recent photos
     *
     * @param int $numRecentPhotos
     */
    private function setNumRecentPhotos($numRecentPhotos)
    {
        $this->numRecentPhotos = $numRecentPhotos;
    }

    /**
     * Get num recent photos
     *
     * @return int
     */
    private function getNumRecentPhotos()
    {
        return $this->numRecentPhotos;
    }

    /**
     * @return \Schoolbox\User
     */
    private function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * @param  \Schoolbox\User $currentUser
     * @return $this
     */
    private function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
        return $this;
    }
}
