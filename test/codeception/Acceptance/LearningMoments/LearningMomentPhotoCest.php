<?php

namespace Tests\Acceptance\LearningMoments;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;
use Exception;
use Tests\Support\Page\LearningMoments\LearningMoments;

class LearningMomentPhotoCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
        $learningMoments = new LearningMoments($i);
        $i->amOnPage($learningMoments->getStudentProfilePage(25));
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * click the link to share a learning moment in the student profile page
     * choose take a picture
     * delete the photo & confirm it (as it is a fake webcam the picture is already placed - in codeception.dist.yml file)
     * click to take a picture
     * write a description
     * submit
     * check the learning moment is presented in the student profile page
     */
    public function testLearningMomentPhoto(AcceptanceTester $i): void
    {
        $learningMoments = new LearningMoments($i);
        $i->amGoingTo('share a photo learning moment');
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $i->amGoingTo('ensure I am able to delete the photo and then take the photo again');
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test - take a photo');
        $learningMoments->clickSubmit();
        $i->amGoingTo('checking if the picture and text are presented in the student activity feed');
        $i->waitForText('This is a learning moments test - take a photo');
        $i->waitForElement('img[alt="photo.jpeg"]', 2);
        $i->amGoingTo('check if the Share Learning Moment button is presented');
        $i->waitForElement('a.learning.icon-share', 2);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * add a learning moment
     * comment the learning moment
     * check the comment is presented
     */
    public function testLearningMomentComments(AcceptanceTester $i): void
    {
        $learningMoments = new LearningMoments($i);
        $i->amOnPage($learningMoments->getStudentProfilePage(6));
        $i->amGoingTo('share a learning moment');
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test to be commented');
        $learningMoments->clickSubmit();
        $i->amGoingTo('check the learning moment is presented in the student activity feed and comment it');
        $i->waitForText('This is a learning moments test to be commented');
        $learningMoments->clickAddComment();
        $i->waitForElementVisible(['css' => '#comment-0 + div.ck'], 5);
        $learningMoments->typeComment();
        sleep(2);
        $learningMoments->clickSubmitComment();
        $i->amGoingTo('checking the comment is presented');
        $i->waitForText('Well done. This is my first comment in this learning moment!', 2);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * add a learning moment
     * modify the learning moment
     * check the learning moment is updated properly in the activity feed
     */
    public function testLearningMomentModify(AcceptanceTester $i): void
    {
        $learningMoments = new LearningMoments($i);
        $i->amOnPage($learningMoments->getStudentProfilePage(45));
        $i->amGoingTo('share a learning moment');
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test to be modified');
        $learningMoments->clickSubmit();
        $i->amGoingTo('checking the learning moment is presented in the student activity feed and getting it updated');
        $i->waitForText('This is a learning moments test to be modified');
        $learningMoments->clickDropdownMenu(3);
        $learningMoments->clickModifySubmenu();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test to be modified updated');
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->clickSubmit();
        $i->amGoingTo('checking the learning moment is presented in the student activity feed updated');
        $i->waitForText('This is a learning moments test to be modified updated');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * add a learning moment
     * comment the learning moment
     * delete the comment
     * check the comment is no longer presented
     */
    public function testLearningMomentDeleteComment(AcceptanceTester $i): void
    {
        $learningMoments = new LearningMoments($i);
        $i->amOnPage($learningMoments->getStudentProfilePage(60));
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test to get comments deleted');
        $learningMoments->clickSubmit();
        $i->amGoingTo('checking the learning moment is presented in the student activity feed and adding a comment');
        $i->waitForText('This is a learning moments test to get comments deleted');
        $learningMoments->clickAddComment();
        $learningMoments->typeComment();
        sleep(2);
        $learningMoments->clickSubmitComment();
        $i->amGoingTo('checking the comment is presented');
        $i->waitForText('Well done. This is my first comment in this learning moment!', 2);
        $i->amGoingTo('delete the learning moments comment');
        $learningMoments->clickCommentDeleteButton();
        sleep(2);
        $i->click(Locator::lastElement('button.button.submit'));
        $i->amGoingTo('checking the comment is no longer presented and the count is zero');
        $i->cantSee('Well done. This is my first comment in this learning moment!');
        $i->canSee('Comments (0)');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * add a learning moment
     * delete the learning moment
     * check the learning moment is no longer presented
     */
    public function testLearningMomentDelete(AcceptanceTester $i): void
    {
        $learningMoments = new LearningMoments($i);
        $i->amOnPage($learningMoments->getStudentProfilePage(29));
        $i->amGoingTo('share a learning moment');
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moment test to get deleted');
        $learningMoments->clickSubmit();
        $i->amGoingTo('checking the learning moment is presented in the student activity feed');
        $i->waitForText('This is a learning moment test to get deleted');
        $i->amGoingTo('deleting the learning moment');
        $learningMoments->clickDropdownMenu(5);
        $learningMoments->clickLearningMomentDeleteButton();
        sleep(2);
        $i->click(Locator::lastElement('a.button.submit'));
        sleep(2);
        $i->amGoingTo('checking the text is no longer presented ');
        $i->cantSee('This is a learning moment test to get deleted');
        $i->canSeeElement($learningMoments::SHARE_LEARNING_MOMENTS_LINK);
    }
}
