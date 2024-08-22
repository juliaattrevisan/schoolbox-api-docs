/**
 * This test checks if the components are presented under the proper tab in a homepage
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

test.describe("Homepage Components Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Homepage Components", async ({ tester, page }) => {
        const homepage = new Homepage(page);
        await homepage.goto(83);

        tester.isGoingTo("click the add component icon");
        await homepage.openTabPanel(Homepage.ELEMENT_ADD_COMPONENT_TAB);

        tester.isGoingTo("check the tabs present the proper numbers of components");
        const homepageTabsEl = page.getByTestId("homepage-tabs");

        await expect(homepageTabsEl).toContainText("Information (7)");
        await expect(homepageTabsEl).toContainText("Collaboration (10)");
        await expect(homepageTabsEl).toContainText("Learning (12)");
        await expect(homepageTabsEl).toContainText("Multimedia (9)");
        await expect(homepageTabsEl).toContainText("Resources (4)");
        await expect(homepageTabsEl).toContainText("Feeds (5)");

        tester.isGoingTo("check the tab Information presents the proper components");
        await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_INFORMATION);

        await expect(homepageTabsEl).toContainText("Blog");
        await homepage.checkComponentIcon("Schoolbox_Blog_Homepage_Component_Controller");

        await expect(homepageTabsEl).toContainText("Calendar");
        await homepage.checkComponentIcon("Schoolbox_Calendar_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Countdown");
        await homepage.checkComponentIcon("Component_Homepage_CountdownController");

        await expect(homepageTabsEl).toContainText("News");
        await homepage.checkComponentIcon("Schoolbox_Comms_News_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Team Fixtures");
        await homepage.checkComponentIcon("Schoolbox_Fixtures_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Text Box");
        await homepage.checkComponentIcon("Schoolbox_Resource_Textbox_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Tiles");
        await homepage.checkComponentIcon("Schoolbox_Tile_Component_HomepageTileController");

        tester.isGoingTo("check the tab Collaboration presents the proper components");
        await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_COLLABORATION);

        await expect(homepageTabsEl).toContainText("Chat");
        await homepage.checkComponentIcon("Component_Homepage_ChatController");

        await expect(homepageTabsEl).toContainText("Collab Docs");
        await homepage.checkComponentIcon("Component_Homepage_EtherpadController");

        await expect(homepageTabsEl).toContainText("Forum");
        await homepage.checkComponentIcon("Component_Homepage_ForumListController");

        await expect(homepageTabsEl).toContainText("Google Files");
        await homepage.checkComponentIcon("Schoolbox_Resource_GoogleFile_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Group Managers");
        await homepage.checkComponentIcon("Component_Homepage_ManagersController");

        await expect(homepageTabsEl).toContainText("Group Members");
        await homepage.checkComponentIcon("Component_Homepage_MembersController");

        await expect(homepageTabsEl).toContainText("Poll");
        await homepage.checkComponentIcon("Component_Homepage_PollController");

        await expect(homepageTabsEl).toContainText("Social Stream");
        await homepage.checkComponentIcon("Component_Homepage_SocialStreamController");

        await expect(homepageTabsEl).toContainText("Surveys and Forms");
        await homepage.checkComponentIcon("Component_Homepage_SurveyController");

        await expect(homepageTabsEl).toContainText("Wiki");
        await homepage.checkComponentIcon("Component_Homepage_WikiController");

        tester.isGoingTo("check the tab Learning presents the proper components");
        await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_LEARNING);

        await expect(homepageTabsEl).toContainText("Class List");
        await homepage.checkComponentIcon("Component_Homepage_ClassListController");

        await expect(homepageTabsEl).toContainText("Class Teachers");
        await homepage.checkComponentIcon("Component_Homepage_TeachersController");

        await expect(homepageTabsEl).toContainText("Course Builder");
        await homepage.checkComponentIcon("Schoolbox_Learning_Assessment_Component_Homepage_Builder_Controller");

        await expect(homepageTabsEl).toContainText("Course Outline");
        await homepage.checkComponentIcon("Schoolbox_Learning_Assessment_Component_Homepage_Outline_Controller");

        await expect(homepageTabsEl).toContainText("Curriculum Description");
        await homepage.checkComponentIcon("Schoolbox_Curriculum_Component_CurriculumDetailsController");

        await expect(homepageTabsEl).toContainText("Curriculum Overview");
        await homepage.checkComponentIcon("Schoolbox_Curriculum_Component_CurriculumOverviewController");

        await expect(homepageTabsEl).toContainText("Due Work");
        await homepage.checkComponentIcon("Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("File Drop Box");
        await homepage.checkComponentIcon("Component_Homepage_DropBoxController");

        await expect(homepageTabsEl).toContainText("Quizzes");
        await homepage.checkComponentIcon("Schoolbox_Learning_Assessment_Quiz_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Rubric Capabilities and Indicators");
        await homepage.checkComponentIcon("Component_Homepage_RubricController");

        await expect(homepageTabsEl).toContainText("Tasks");
        await homepage.checkComponentIcon("Schoolbox_Learning_Assessment_Task_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Lesson Plan");
        await homepage.checkComponentIcon("Schoolbox_Learning_Component_Homepage_CurrentLessonPlanController");

        tester.isGoingTo("check the tab Multimedia presents the proper components");
        await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_MULTIMEDIA);

        await expect(homepageTabsEl).toContainText("Audio");
        await homepage.checkComponentIcon("Component_Homepage_AudioController");

        await expect(homepageTabsEl).toContainText("ClickView");
        await homepage.checkComponentIcon("Component_Homepage_ClickviewController");

        await expect(homepageTabsEl).toContainText("Embed Learning Tool");
        await homepage.checkComponentIcon("Schoolbox_LTI_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("GeoSnapShot");
        await homepage.checkComponentIcon("Schoolbox_Geosnapshot_Component_Homepage_Controller");

        await expect(homepageTabsEl).toContainText("Image");
        await homepage.checkComponentIcon("Component_Homepage_ImageController");

        await expect(homepageTabsEl).toContainText("Image Gallery");
        await homepage.checkComponentIcon("Component_Homepage_ImageGalleryController");

        await expect(homepageTabsEl).toContainText("Image Slideshow");
        await homepage.checkComponentIcon("Component_Homepage_SlideshowController");

        await expect(homepageTabsEl).toContainText("Random Image");
        await homepage.checkComponentIcon("Component_Homepage_RandImageController");

        await expect(homepageTabsEl).toContainText("Video Player");
        await homepage.checkComponentIcon("Component_Homepage_VideoController");

        tester.isGoingTo("check the tab Resources presents the proper components");
        await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_RESOURCES);

        await expect(homepageTabsEl).toContainText("Breadcrumbs");
        await homepage.checkComponentIcon("Component_Homepage_BreadcrumbController");

        await expect(homepageTabsEl).toContainText("Files");
        await homepage.checkComponentIcon("Component_Homepage_FileListController");

        await expect(homepageTabsEl).toContainText("Folders");
        await homepage.checkComponentIcon("Component_Homepage_FolderListController");

        await expect(homepageTabsEl).toContainText("Links");
        await homepage.checkComponentIcon("Component_Homepage_LinkListController");

        tester.isGoingTo("check the tab Feeds presents the proper components");
        await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_FEEDS);

        await expect(homepageTabsEl).toContainText("Homepage Feed");
        await homepage.checkComponentIcon("Component_Homepage_FeedController");

        await expect(homepageTabsEl).toContainText("Pinterest");
        await homepage.checkComponentIcon("Component_Homepage_PinterestController");

        await expect(homepageTabsEl).toContainText("Podcast");
        await homepage.checkComponentIcon("Component_Homepage_PodcastController");

        await expect(homepageTabsEl).toContainText("RSS Feed");
        await homepage.checkComponentIcon("Component_Homepage_RSSController");

        await expect(homepageTabsEl).toContainText("Wolfram|Alpha");
        await homepage.checkComponentIcon("Component_Homepage_WolframController");
    });
});
