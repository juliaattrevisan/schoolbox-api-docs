<?php

namespace LearningMoments;

use AcceptanceTester;
use Exception;
use Page\LearningMoments\LearningMoments;

class LearningMomentPhotoCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i)
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
    public function testLearningMomentPhoto(AcceptanceTester $i)
    {
        $learningMoments = new LearningMoments($i);
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test - take a photo');
        $learningMoments->clickSubmit();
        //checking if the picture and text are presented in the student activity feed
        $i->waitForText('This is a learning moments test - take a photo');
        $i->waitForElement('img[alt="photo.jpeg"]', 2);
        //check if the Share Learning Moment button is presented
        $i->waitForElement('a.learning.icon-share', 2);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * add a learning moment
     * comment the learning moment
     * check the comment is presented
     */
    public function testLearningMomentComments(AcceptanceTester $i)
    {
        $learningMoments = new LearningMoments($i);
        $i->amOnPage($learningMoments->getStudentProfilePage(6));
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test to be commented');
        $learningMoments->clickSubmit();
        //checking the learning moment is presented in the student activity feed
        $i->waitForText('This is a learning moments test to be commented');
        $learningMoments->clickAddComment();
        $i->waitForElementVisible(['css' => 'div#cke_1_contents'], 5);
        $learningMoments->typeComment();
        sleep(2);
        $learningMoments->clickSubmitComment();
        //checking the comment is presented
        $i->waitForText('Well done. This is my first comment in this learning moment!', 2);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * add a learning moment
     * modify the learning moment
     * check the learning moment is updated properly in the activity feed
     */
    public function testLearningMomentModify(AcceptanceTester $i)
    {
        $learningMoments = new LearningMoments($i);
        $i->amOnPage($learningMoments->getStudentProfilePage(45));
        $learningMoments->clickShareLearningMomentLink();
        $learningMoments->clickTakeAPhoto();
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test to be modified');
        $learningMoments->clickSubmit();
        //checking the learning moment is presented in the student activity feed
        $i->waitForText('This is a learning moments test to be modified');
        $learningMoments->clickDropdownMenu();
        $learningMoments->clickModifySubmenu();
        $i->fillCkEditorById('evidence-body', 'This is a learning moments test to be modified updated');
        $learningMoments->deletePhotoEvidence();
        $learningMoments->confirmDeletePhoto();
        sleep(2);
        $learningMoments->clickTakeAPhotoEvidence();
        $learningMoments->clickSubmit();
        //checking the learning moment is presented in the student activity feed updated
        $i->waitForText('This is a learning moments test to be modified updated');
    }
}
