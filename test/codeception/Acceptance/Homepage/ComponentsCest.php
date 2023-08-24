<?php

namespace Tests\Acceptance\Homepage;

use Tests\Support\AcceptanceTester;
use Exception;
use Tests\Support\Page\Homepage;

class ComponentsCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to https://vagrant.dev.schoolbox.cloud/homepage/83
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
        $homepage = new Homepage($i);
        $i->amOnPage($homepage->generateRoute(83));
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * navigate to a homepage
     * check components are presented under the proper tabs
     */
    public function checkComponentsTabs(AcceptanceTester $i): void
    {
        $homepage = new Homepage($i);
        $i->amGoingTo('Click the add component icon');
        $homepage->openTabPanel($homepage::ELEMENT_ADD_COMPONENT_TAB);
        $i->amGoingTo('Check the tabs present the proper numbers of components');
        $i->canSee('Information (7)');
        $i->canSee('Collaboration (10)');
        $i->canSee('Learning (12)');
        $i->canSee('Multimedia (9)');
        $i->canSee('Resources (4)');
        $i->canSee('Feeds (5)');

        $i->amGoingTo('Check the tab Information presents the proper components');
        $homepage->clickComponentsTab($homepage::ELEMENT_COMPONENT_SECTION_INFORMATION);
        $homepage->checkComponentIcon('Schoolbox_Blog_Homepage_Component_Controller');
        $i->canSee('Blog');
        $homepage->checkComponentIcon('Schoolbox_Blog_Homepage_Component_Controller');
        $i->canSee('Calendar');
        $homepage->checkComponentIcon('Schoolbox_Calendar_Component_Homepage_Controller');
        $i->canSee('Countdown');
        $homepage->checkComponentIcon('Component_Homepage_CountdownController');
        $i->canSee('News');
        $homepage->checkComponentIcon('Schoolbox_Comms_News_Component_Homepage_Controller');
        $i->canSee('Team Fixtures');
        $homepage->checkComponentIcon('Schoolbox_Fixtures_Component_Homepage_Controller');
        $i->canSee('Text Box');
        $homepage->checkComponentIcon('Schoolbox_Resource_Textbox_Component_Homepage_Controller');
        $i->canSee('Tiles');
        $homepage->checkComponentIcon('Schoolbox_Tile_Component_HomepageTileController');

        $i->amGoingTo('Check the tab Collaboration presents the proper components');
        $homepage->clickComponentsTab($homepage::ELEMENT_COMPONENT_SECTION_COLLABORATION);
        $homepage->checkComponentIcon('Component_Homepage_ChatController');
        $i->canSee('Chat');
        $homepage->checkComponentIcon('Component_Homepage_EtherpadController');
        $i->canSee('Collab Docs');
        $homepage->checkComponentIcon('Component_Homepage_ForumListController');
        $i->canSee('Forum');
        $homepage->checkComponentIcon('Schoolbox_Resource_GoogleFile_Component_Homepage_Controller');
        $i->canSee('Google Files');
        $homepage->checkComponentIcon('Component_Homepage_ManagersController');
        $i->canSee('Group Managers');
        $homepage->checkComponentIcon('Component_Homepage_MembersController');
        $i->canSee('Group Members');
        $homepage->checkComponentIcon('Component_Homepage_PollController');
        $i->canSee('Poll');
        $homepage->checkComponentIcon('Component_Homepage_SocialStreamController');
        $i->canSee('Social Stream');
        $homepage->checkComponentIcon('Component_Homepage_SurveyController');
        $i->canSee('Surveys and Forms');
        $homepage->checkComponentIcon('Component_Homepage_WikiController');
        $i->canSee('Wiki');

        $i->amGoingTo('Check the tab Learning presents the proper components');
        $homepage->clickComponentsTab($homepage::ELEMENT_COMPONENT_SECTION_LEARNING);
        $homepage->checkComponentIcon('Component_Homepage_ClassListController');
        $i->canSee('Class List');
        $homepage->checkComponentIcon('Component_Homepage_TeachersController');
        $i->canSee('Class Teachers');
        $homepage->checkComponentIcon('Schoolbox_Learning_Assessment_Component_Homepage_Builder_Controller');
        $i->canSee('Course Builder');
        $homepage->checkComponentIcon('Schoolbox_Learning_Assessment_Component_Homepage_Outline_Controller');
        $i->canSee('Course Outline');
        $homepage->checkComponentIcon('Schoolbox_Curriculum_Component_CurriculumDetailsController');
        $i->canSee('Curriculum Description');
        $homepage->checkComponentIcon('Schoolbox_Curriculum_Component_CurriculumOverviewController');
        $i->canSee('Curriculum Overview');
        $homepage->checkComponentIcon('Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller');
        $i->canSee('Due Work');
        $homepage->checkComponentIcon('Component_Homepage_DropBoxController');
        $i->canSee('File Drop Box');
        $homepage->checkComponentIcon('Schoolbox_Learning_Assessment_Quiz_Component_Homepage_Controller');
        $i->canSee('Quizzes');
        $homepage->checkComponentIcon('Component_Homepage_RubricController');
        $i->canSee('Rubric Capabilities and Indicators');
        $homepage->checkComponentIcon('Schoolbox_Learning_Assessment_Task_Component_Homepage_Controller');
        $i->canSee('Tasks');
        $homepage->checkComponentIcon('Schoolbox_Learning_Component_Homepage_CurrentLessonPlanController');
        $i->canSee('Lesson Plan');

        $i->amGoingTo('Check the tab Multimedia presents the proper components');
        $homepage->clickComponentsTab($homepage::ELEMENT_COMPONENT_SECTION_MULTIMEDIA);
        $homepage->checkComponentIcon('Component_Homepage_AudioController');
        $i->canSee('Audio');
        $homepage->checkComponentIcon('Component_Homepage_ClickviewController');
        $i->canSee('ClickView');
        $homepage->checkComponentIcon('Schoolbox_LTI_Component_Homepage_Controller');
        $i->canSee('Embed Learning Tool');
        $homepage->checkComponentIcon('Schoolbox_Geosnapshot_Component_Homepage_Controller');
        $i->canSee('GeoSnapShot');
        $homepage->checkComponentIcon('Component_Homepage_ImageController');
        $i->canSee('Image');
        $homepage->checkComponentIcon('Component_Homepage_ImageGalleryController');
        $i->canSee('Image Gallery');
        $homepage->checkComponentIcon('Component_Homepage_SlideshowController');
        $i->canSee('Image Slideshow');
        $homepage->checkComponentIcon('Component_Homepage_RandImageController');
        $i->canSee('Random Image');
        $homepage->checkComponentIcon('Component_Homepage_VideoController');
        $i->canSee('Video Player');

        $i->amGoingTo('Check the tab Resources presents the proper components');
        $homepage->clickComponentsTab($homepage::ELEMENT_COMPONENT_SECTION_RESOURCES);
        $homepage->checkComponentIcon('Component_Homepage_BreadcrumbController');
        $i->canSee('Breadcrumbs');
        $homepage->checkComponentIcon('Component_Homepage_FileListController');
        $i->canSee('Files');
        $homepage->checkComponentIcon('Component_Homepage_FolderListController');
        $i->canSee('Folders');
        $homepage->checkComponentIcon('Component_Homepage_LinkListController');
        $i->canSee('Links');

        $i->amGoingTo('Check the tab Feeds presents the proper components');
        $homepage->clickComponentsTab($homepage::ELEMENT_COMPONENT_SECTION_FEEDS);
        $homepage->checkComponentIcon('Component_Homepage_FeedController');
        $i->canSee('Homepage Feed');
        $homepage->checkComponentIcon('Component_Homepage_PinterestController');
        $i->canSee('Pinterest');
        $homepage->checkComponentIcon('Component_Homepage_PodcastController');
        $i->canSee('Podcast');
        $homepage->checkComponentIcon('Component_Homepage_RSSController');
        $i->canSee('RSS Feed');
        $homepage->checkComponentIcon('Component_Homepage_WolframController');
        $i->canSee('Wolfram|Alpha');
    }
}
