<?php
declare(strict_types=1);

namespace Page\LearningMoments;

use AcceptanceTester;

class LearningMoments
{
    public const URL = '/search/user/{student_id}';
    public const URL_STUDENT_ID = '{student_id}';

    public const SHARE_LEARNING_MOMENTS_LINK = 'a[title="Share a learning moment"]';
    public const TAKE_A_PHOTO_BUTTON = 'a.icon-camera.share-photo';
    public const TAKE_A_PHOTO_BUTTON_EVIDENCE = 'button.vjs-camera-button.vjs-control.vjs-button.vjs-icon-photo-camera';
    public const DELETE_A_PHOTO = 'a.button.icon-delete.delete-button';
    public const CONFIRM_BUTTON = 'Confirm';
    public const SUBMIT_BUTTON = '#evidence-submit';

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
     * click "Take a photo" button on the modal
     */
    public function clickTakeAPhoto(): void
    {
        $this->tester->click(self::TAKE_A_PHOTO_BUTTON);
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

}
