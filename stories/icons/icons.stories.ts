import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Below is a list of all icons, which can be referenced by their name and used within the `<sbx-icon>` [component](?path=/docs/components-icon--docs).
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Icons/Icons",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
        <div class="l-flex-row l-flex--center l-gap-s">
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="add"></sbx-icon>
                </div>
                <p>add</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="advanced-list"></sbx-icon>
                </div>
                <p>advanced-list</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="alert"></sbx-icon>
                </div>
                <p>alert</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="anchor"></sbx-icon>
                </div>
                <p>anchor</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="approve"></sbx-icon>
                </div>
                <p>approve</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="archive"></sbx-icon>
                </div>
                <p>archive</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="attach"></sbx-icon>
                </div>
                <p>attach</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="audio"></sbx-icon>
                </div>
                <p>audio</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="badge"></sbx-icon>
                </div>
                <p>badge</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="boarder-leave"></sbx-icon>
                </div>
                <p>boarder-leave</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="breadcrumbs"></sbx-icon>
                </div>
                <p>breadcrumbs</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="calendar"></sbx-icon>
                </div>
                <p>calendar</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="chat"></sbx-icon>
                </div>
                <p>chat</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="class-list"></sbx-icon>
                </div>
                <p>class-list</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="click-view"></sbx-icon>
                </div>
                <p>click-view</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="clipboard"></sbx-icon>
                </div>
                <p>clipboard</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="close"></sbx-icon>
                </div>
                <p>close</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="cloud"></sbx-icon>
                </div>
                <p>cloud</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="collab-docs"></sbx-icon>
                </div>
                <p>collab-docs</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="collapse"></sbx-icon>
                </div>
                <p>collapse</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="comment"></sbx-icon>
                </div>
                <p>comment</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="components"></sbx-icon>
                </div>
                <p>components</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="copy"></sbx-icon>
                </div>
                <p>copy</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="countdown"></sbx-icon>
                </div>
                <p>countdown</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="course-builder"></sbx-icon>
                </div>
                <p>course-builder</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="course-outline"></sbx-icon>
                </div>
                <p>course-outline</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="criteria"></sbx-icon>
                </div>
                <p>criteria</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="curriculum"></sbx-icon>
                </div>
                <p>curriculum</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="curriculum-details"></sbx-icon>
                </div>
                <p>curriculum-details</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="danger"></sbx-icon>
                </div>
                <p>danger</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="day-view"></sbx-icon>
                </div>
                <p>day-view</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="delete"></sbx-icon>
                </div>
                <p>delete</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="directory"></sbx-icon>
                </div>
                <p>directory</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="division"></sbx-icon>
                </div>
                <p>division</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="document"></sbx-icon>
                </div>
                <p>document</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="dollar"></sbx-icon>
                </div>
                <p>dollar</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="down-arrow"></sbx-icon>
                </div>
                <p>down-arrow</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="download"></sbx-icon>
                </div>
                <p>download</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="drag-drop"></sbx-icon>
                </div>
                <p>drag-drop</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="drop-box"></sbx-icon>
                </div>
                <p>drop-box</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="dropdown"></sbx-icon>
                </div>
                <p>dropdown</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="due-work"></sbx-icon>
                </div>
                <p>due-work</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="edit-columns"></sbx-icon>
                </div>
                <p>edit-columns</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="education-maintenance"></sbx-icon>
                </div>
                <p>education-maintenance</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="email"></sbx-icon>
                </div>
                <p>email</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="eng-second-lang"></sbx-icon>
                </div>
                <p>eng-second-lang</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="engage-logo"></sbx-icon>
                </div>
                <p>engage-logo</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="eportfolio"></sbx-icon>
                </div>
                <p>eportfolio</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="equals"></sbx-icon>
                </div>
                <p>equals</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="export"></sbx-icon>
                </div>
                <p>export</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="external-link"></sbx-icon>
                </div>
                <p>external-link</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="favourite"></sbx-icon>
                </div>
                <p>favourite</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="favourite-hollow"></sbx-icon>
                </div>
                <p>favourite-hollow</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="files"></sbx-icon>
                </div>
                <p>files</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="filter"></sbx-icon>
                </div>
                <p>filter</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="flame"></sbx-icon>
                </div>
                <p>flame</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="folder"></sbx-icon>
                </div>
                <p>folder</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="forms"></sbx-icon>
                </div>
                <p>forms</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="forum"></sbx-icon>
                </div>
                <p>forum</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="geosnapshot"></sbx-icon>
                </div>
                <p>geosnapshot</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="goal"></sbx-icon>
                </div>
                <p>goal</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="google-drive"></sbx-icon>
                </div>
                <p>google-drive</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="grip"></sbx-icon>
                </div>
                <p>grip</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="group"></sbx-icon>
                </div>
                <p>group</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="group-managers"></sbx-icon>
                </div>
                <p>group-managers</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="heart-like"></sbx-icon>
                </div>
                <p>heart-like</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="help"></sbx-icon>
                </div>
                <p>help</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="hidden"></sbx-icon>
                </div>
                <p>hidden</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="history"></sbx-icon>
                </div>
                <p>history</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="home"></sbx-icon>
                </div>
                <p>home</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="homepagefeed"></sbx-icon>
                </div>
                <p>homepagefeed</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="image"></sbx-icon>
                </div>
                <p>image</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="image-gallery"></sbx-icon>
                </div>
                <p>image-gallery</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="image-slideshow"></sbx-icon>
                </div>
                <p>image-slideshow</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="info"></sbx-icon>
                </div>
                <p>info</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="integration-student"></sbx-icon>
                </div>
                <p>integration-student</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="intellischool"></sbx-icon>
                </div>
                <p>intellischool</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="international-baccalaureate"></sbx-icon>
                </div>
                <p>international-baccalaureate</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="international-student"></sbx-icon>
                </div>
                <p>international-student</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="lesson-plan"></sbx-icon>
                </div>
                <p>lesson-plan</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="link"></sbx-icon>
                </div>
                <p>link</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="list"></sbx-icon>
                </div>
                <p>list</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="list-view"></sbx-icon>
                </div>
                <p>list-view</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="live-marks"></sbx-icon>
                </div>
                <p>live-marks</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="location"></sbx-icon>
                </div>
                <p>location</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="locked"></sbx-icon>
                </div>
                <p>locked</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="logout"></sbx-icon>
                </div>
                <p>logout</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="lti"></sbx-icon>
                </div>
                <p>lti</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="match"></sbx-icon>
                </div>
                <p>match</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="medical"></sbx-icon>
                </div>
                <p>medical</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="menu"></sbx-icon>
                </div>
                <p>menu</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="mic"></sbx-icon>
                </div>
                <p>mic</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="mic-muted"></sbx-icon>
                </div>
                <p>mic-muted</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="mic-recording"></sbx-icon>
                </div>
                <p>mic-recording</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="microsoft-teams"></sbx-icon>
                </div>
                <p>microsoft-teams</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="mobile"></sbx-icon>
                </div>
                <p>mobile</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="modify-pen"></sbx-icon>
                </div>
                <p>modify-pen</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="month-view"></sbx-icon>
                </div>
                <p>month-view</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="more"></sbx-icon>
                </div>
                <p>more</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="mostly-cloudy"></sbx-icon>
                </div>
                <p>mostly-cloudy</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="mute"></sbx-icon>
                </div>
                <p>mute</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="network"></sbx-icon>
                </div>
                <p>network</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="news"></sbx-icon>
                </div>
                <p>news</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="next"></sbx-icon>
                </div>
                <p>next</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="notifications"></sbx-icon>
                </div>
                <p>notifications</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="notifications-hollow"></sbx-icon>
                </div>
                <p>notifications-hollow</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="office-365"></sbx-icon>
                </div>
                <p>office-365</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="open"></sbx-icon>
                </div>
                <p>open</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="pause"></sbx-icon>
                </div>
                <p>pause</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="phone"></sbx-icon>
                </div>
                <p>phone</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="pin-hollow"></sbx-icon>
                </div>
                <p>pin-hollow</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="pinterest"></sbx-icon>
                </div>
                <p>pinterest</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="podcast"></sbx-icon>
                </div>
                <p>podcast</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="poll"></sbx-icon>
                </div>
                <p>poll</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="position"></sbx-icon>
                </div>
                <p>position</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="previous"></sbx-icon>
                </div>
                <p>previous</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="print"></sbx-icon>
                </div>
                <p>print</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="progress"></sbx-icon>
                </div>
                <p>progress</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="project"></sbx-icon>
                </div>
                <p>project</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="quiz"></sbx-icon>
                </div>
                <p>quiz</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="rain"></sbx-icon>
                </div>
                <p>rain</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="random-image"></sbx-icon>
                </div>
                <p>random-image</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="reach"></sbx-icon>
                </div>
                <p>reach</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="refresh"></sbx-icon>
                </div>
                <p>refresh</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="reply"></sbx-icon>
                </div>
                <p>reply</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="resource-booking"></sbx-icon>
                </div>
                <p>resource-booking</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="rss"></sbx-icon>
                </div>
                <p>rss</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="schoolbox"></sbx-icon>
                </div>
                <p>schoolbox</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="screen-capture"></sbx-icon>
                </div>
                <p>screen-capture</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="search"></sbx-icon>
                </div>
                <p>search</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="settings"></sbx-icon>
                </div>
                <p>settings</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="share"></sbx-icon>
                </div>
                <p>share</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="showcase"></sbx-icon>
                </div>
                <p>showcase</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="shuffle"></sbx-icon>
                </div>
                <p>shuffle</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="social-stream"></sbx-icon>
                </div>
                <p>social-stream</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="sort-up"></sbx-icon>
                </div>
                <p>sort-up</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="sport"></sbx-icon>
                </div>
                <p>sport</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="staff"></sbx-icon>
                </div>
                <p>staff</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="staff-students"></sbx-icon>
                </div>
                <p>staff-students</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="sun"></sbx-icon>
                </div>
                <p>sun</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="surveys"></sbx-icon>
                </div>
                <p>surveys</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="synergetic"></sbx-icon>
                </div>
                <p>synergetic</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="task"></sbx-icon>
                </div>
                <p>task</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="teacher"></sbx-icon>
                </div>
                <p>teacher</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="textbox"></sbx-icon>
                </div>
                <p>textbox</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="thunder"></sbx-icon>
                </div>
                <p>thunder</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="tiles"></sbx-icon>
                </div>
                <p>tiles</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="time-value"></sbx-icon>
                </div>
                <p>time-value</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="timetable"></sbx-icon>
                </div>
                <p>timetable</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="twitter"></sbx-icon>
                </div>
                <p>twitter</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="undo"></sbx-icon>
                </div>
                <p>undo</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="unit"></sbx-icon>
                </div>
                <p>unit</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="unlocked"></sbx-icon>
                </div>
                <p>unlocked</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="up-arrow"></sbx-icon>
                </div>
                <p>up-arrow</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="up-down"></sbx-icon>
                </div>
                <p>up-down</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="upload"></sbx-icon>
                </div>
                <p>upload</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="video"></sbx-icon>
                </div>
                <p>video</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="video-error"></sbx-icon>
                </div>
                <p>video-error</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="view"></sbx-icon>
                </div>
                <p>view</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="week-view"></sbx-icon>
                </div>
                <p>week-view</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="wiki"></sbx-icon>
                </div>
                <p>wiki</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="withdraw-achievement"></sbx-icon>
                </div>
                <p>withdraw-achievement</p>
            </div>
            <div class="box-icon-wrapper">
                <div class="box-icon">
                    <sbx-icon name="wolfram-alpha"></sbx-icon>
                </div>
                <p>wolfram-alpha</p>
            </div>
        </div>
        `,
    }),
};
