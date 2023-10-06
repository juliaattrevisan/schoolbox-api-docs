<?php

declare(strict_types=1);

namespace Tests\Support\Page\LearningMoments;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

class LearningMoments
{
    public const URL = '/search/user/{student_id}';
    public const URL_STUDENT_ID = '{student_id}';

    public const SHARE_LEARNING_MOMENTS_LINK = 'a[data-reveal-id="shareLearningMoment"]';
    public const SHARE_LEARNING_MOMENTS_BUTTON = 'a.learning.icon-share';
    public const TAKE_A_PHOTO_BUTTON = 'a.icon-camera.share-photo';
    public const TAKE_WRITE_SOMETHING = 'a.icon-textbox.share-text';
    public const TAKE_A_PHOTO_BUTTON_EVIDENCE = 'button.vjs-camera-button.vjs-control.vjs-button.vjs-icon-photo-camera';
    public const DELETE_A_PHOTO = 'a.button.icon-delete.delete-button';
    public const CONFIRM_BUTTON = 'Confirm';
    public const SUBMIT_BUTTON = '#evidence-submit';
    public const ADD_COMMENT_BUTTON = 'button.show-ckeditor-button';
    public const COMMENT_SUBMIT_BUTTON = 'button[class=button\ submit]';
    public const COMMENT_DELETE_BUTTON = 'a[title="Delete comment"]';
    public const COMMENT_CONFIRM_DELETE_BUTTON = 'ID to be added and replaced here';
    public const LEARNING_MOMENT_DELETE_BUTTON = 'a[title="Delete"]';

    /**
     * This test's acceptance tester actor.
     */
    private AcceptanceTester $tester;

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
        $this->tester->fillCkEditorById('comment-0', 'Well done. This is my first comment in this learning moment!');
    }

    /**
     * click add a comment
     */
    public function clickSubmitComment(): void
    {
        $this->tester->click(self::COMMENT_SUBMIT_BUTTON);
    }
    /**
     * click the 3-dot dropdown menu by passing its evidence
     * @param int $evidenceId
     */
    public function clickDropdownMenu(int $evidenceId): void
    {
        $this->tester->clickElementInWebComponent('[data-test=sbx-pop-out-evidence-' . $evidenceId . ']', '[data-test=sbx-pop-out-evidence-' . $evidenceId . '-dropdown-button]');
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
    public function clickConfirmDeleteButton(): void
    {
        $this->tester->click(self::COMMENT_CONFIRM_DELETE_BUTTON);
    }

    /**
     * click learning moment Delete button in the 3-dot menu
     * @throws Exception
     */
    public function clickLearningMomentDeleteButton(): void
    {
        $this->tester->click(self::LEARNING_MOMENT_DELETE_BUTTON);
    }
}
