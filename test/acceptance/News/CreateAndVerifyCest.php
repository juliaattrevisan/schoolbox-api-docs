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
        $formPage
            ->setHeadline($headline)
            ->setArticle($article)
            ->setBlurb($blurb)
            ->setFeatureImage($image)
            ->setTopic('General')
            ->setRoles([])
            ->setCampuses([])
            ->setUsers([])
            ->setPublishDate('')
            ->setExpiryDate('')
        ;
    }
}
