<?php
declare(strict_types=1);

namespace Page\LearningMoments;

use AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

class LearningMoments
{
    public const URL = '/search/user/{student_id}';
    public const URL_STUDENT_ID = '{student_id}';

    public const SHARE_LEARNING_MOMENTS_LINK = 'a[title="Share a learning moment"]';
    public const SHARE_LEARNING_MOMENTS_BUTTON = 'a.learning.icon-share';
    public const TAKE_A_PHOTO_BUTTON = 'a.icon-camera.share-photo';
    public const TAKE_WRITE_SOMETHING = 'a.icon-textbox.share-text';
    public const TAKE_A_PHOTO_BUTTON_EVIDENCE = 'button.vjs-camera-button.vjs-control.vjs-button.vjs-icon-photo-camera';
    public const DELETE_A_PHOTO = 'a.button.icon-delete.delete-button';
    public const CONFIRM_BUTTON = 'Confirm';
    public const SUBMIT_BUTTON = '#evidence-submit';
    public const ADD_COMMENT_BUTTON = 'button.btn-show-ckeditor';
    public const COMMENT_SUBMIT_BUTTON = 'a[class=button\ submit]';
    public const DROPDOWN_MENU = 'a.icon-overflow';
    public const COMMENT_DELETE_BUTTON = 'a[title="Delete comment"]';
    public const COMMENT_CONFIRM_DELETE_BUTTON = 'ul.flex-list.buttons li a.button.submit';

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Gets url to create a learning moment for a student
     * @param int $studentId
     * @return string the URL
     */
    public static function getStudentProfilePage(int $studentId): string
    {
        return str_replace(
            [self::URL_STUDENT_ID],
            [$studentId],
            self::URL
        );
    }

    /**
     * click "Share a learning moment" link in the student profile page
     */
    public function clickShareLearningMomentLink(): void
    {
        $this->tester->click(self::SHARE_LEARNING_MOMENTS_LINK);
    }

    /**
     * click "Share a learning moment" button in the student profile page
     */
    public function clickShareLearningMomentButton(): void
    {
        $this->tester->click(self::SHARE_LEARNING_MOMENTS_BUTTON);
    }

    /**
     * click "Take a photo" button on the modal
     */
    public function clickTakeAPhoto(): void
    {
        $this->tester->click(self::TAKE_A_PHOTO_BUTTON);
    }

    /**
     * click "Write something" button on the modal
     */
    public function clickWriteSomething(): void
    {
        $this->tester->click(self::TAKE_WRITE_SOMETHING);
    }

    /**
     * click "Take a photo" button on the create evidence page
     */
    public function clickTakeAPhotoEvidence(): void
    {
        $this->tester->click(self::TAKE_A_PHOTO_BUTTON_EVIDENCE);
    }

    /**
     * click "delete button to remove a photo already taken
     */
    public function deletePhotoEvidence(): void
    {
        $this->tester->click(self::DELETE_A_PHOTO);
    }

    /**
     * click "delete button to remove a photo already taken
     */
    public function confirmDeletePhoto(): void
    {
        $this->tester->click(self::CONFIRM_BUTTON);
    }
    /**
     * click evidence submit button
     */
    public function clickSubmit(): void
    {
        $this->tester->click(self::SUBMIT_BUTTON);
    }
    /**
     * click add a comment
     */
    public function clickAddComment(): void
    {
        $this->tester->click(self::ADD_COMMENT_BUTTON);
    }

    /**
     * typing a comment
     * @throws Exception
     */
    public function typeComment(): void
    {
        $this->tester->waitForElementVisible(['css' => 'div#cke_1_contents'], 5);
        $this->tester->fillCkEditorById('1_contents', 'Well done. This is my first comment in this learning moment!');
        $this->tester->click('a#cke_11');
    }
    /**
     * click add a comment
     */
    public function clickSubmitComment(): void
    {
        $this->tester->click(self::COMMENT_SUBMIT_BUTTON);
    }
    /**
     * click the 3-dot dropdown mennu
     */
    public function clickDropdownMenu(): void
    {
        $this->tester->click(self::DROPDOWN_MENU);
    }

    /**
     * click Modify submenu
     * @throws Exception
     */
    public function clickModifySubmenu(): void
    {
        $element = Locator::contains('div.actions-small-1 ul li a', 'Modify');
        $this->tester->waitForElementVisible($element, 5);
        $this->tester->click($element);
    }

    /**
     * click learning moments comment Delete  button
     * @throws Exception
     */
    public function clickCommentDeleteButton(): void
    {
        $this->tester->click(self::COMMENT_DELETE_BUTTON);
    }

    /**
     * click learning moments confirm Delete  button on modal dialog
     * @throws Exception
     */
    public function clickConfirmCommentDeleteButton(): void
    {
        $this->tester->click(self::COMMENT_CONFIRM_DELETE_BUTTON);
    }
}
