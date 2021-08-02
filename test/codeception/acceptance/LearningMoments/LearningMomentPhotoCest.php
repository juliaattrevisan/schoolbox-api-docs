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
        $i->loginAsAdmin();
        $learningMoments = new LearningMoments($i);;
        $i->amOnPage($learningMoments->getStudentProfilePage(25));
    }

    /**
     * Clean up after every test
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i)
    {
        $i->logout();
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
        $i->waitForText('This is a learning moments test - take a photo');
        $i->waitForElement('img[alt="photo.jpeg"]', 2);
    }
}
