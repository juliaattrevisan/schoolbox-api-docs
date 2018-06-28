<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


/**
 * Class SubmissionHistoryCest
 */

namespace News;

use AcceptanceTester;
use Page\Login as LoginPage;
use Page\News\Form;
use Page\News\Index;

class CreateAndVerifyCest
{
    /**
     * Test that a news item I create retains the settings I chose
     *
     * as zsmith:
     * * go to http://192.168.36.10/news
     * * create a new news item, with:
     * ** headline
     * ** article
     * ** blurb
     * ** feature image
     * ** topic
     * ** limit its audience to teachers
     * * save it
     * * verify that:
     * ** it shows in the All topic
     * ** it shows in the topic I put it in
     * ** it doesn't show in other topics
     * ** its blurb (not article) shows in the topic view
     * ** its headline matches what I entered
     * ** in the article view, its article matches what I entered
     * ** when I modify it, everything I submitted is there
     *
     * @param AcceptanceTester $i
     * @param Form             $formPage
     */
    public function createNews(AcceptanceTester $i, Form $formPage)
    {
        $i->login('zsmith', 'password');
        $i->amOnPage($formPage->generateCreateRoute());

        // Fill in form values
        // TODO these should be randomizable
        $headline = "Random Headline";
        $article  = "Random Article";
        $blurb    = "Blurb";
        $image    = '';
        $topic    = 'General';
        $roles    = ['Teachers'];
        $campi    = ['Senior', 'Junior'];
        $users    = ['David Rhodes', 'Rachel Rhodes'];
        $publish  = \Alaress_Date_Format::now()->addHours(-1)->setTime(0, 0, 0);
        $expiry   = \Alaress_Date_Format::now()->addDays(7)->setTime(0, 0, 0);
//        var_dump($publish->getCalendarDateTime());
        $formPage
            ->setHeadline($headline)
            ->setArticle($article)
            ->setBlurb($blurb)
            ->setFeatureImage($image)
            ->setTopic($topic)
            ->setRoles($roles)
            ->setCampuses($campi)
            ->setUsers($users)
            ->setPublishDate($publish)
            ->setExpiryDate($expiry)
            ->submit();
    }
}
