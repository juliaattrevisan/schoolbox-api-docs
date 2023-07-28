import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Cards/SearchCards",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Assessments: Story = {
    render: () => ({
        template: `
        <div class="sb-card-list">
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-due-work"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Essay - Final Assessment</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-quiz"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Pre-unit Quiz</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-project"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Romeo and Juliet Project</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-task"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Post to Forum: Prologue</h6></a>
                    </div>
                </div>
            </div>
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-lesson-plan"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Romeo and Juliet Character Analysis</h6></a>
                    </div>
                </div>
            </div>
        </div>
        `,
    }),
};

export const Badge: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img--badge">
                        <div class="img__wrapper">
                            <img src="https://schoolbox-static.cloud/23.0.5-1/images/badges/education/85.svg"/>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Year 9 English</h3></a>
                        <span class="meta">Senior Courses > English</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Course: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img">
                        <div class="img__wrapper">
                            <div class="icon icon-course"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Year 9 English</h3></a>
                        <span class="meta">Senior Courses > English</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Event: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img">
                        <div class="img__wrapper">
                            <div class="icon icon-calendar"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Year 9 English</h3></a>
                        <span class="meta">Senior Courses > English</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Files: Story = {
    render: () => ({
        template: `
            <div class="sb-card-list">
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-audio.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.wav</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                            <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-video.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.mp4</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-blank.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.abc</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://schoolbox-static.cloud/23.0.4-4/images/mime/application-pdf.svg"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.pdf</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://placedog.net/120/120"/>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">A_file_name.png</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Forum: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-forum"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">What was the most impactful line in Romeo and Juliet?</h6></a>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Link: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-link"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href="https://www.youtube.com/watch?v=_xc7tNbjnHM" target="_blank"><h6 class="">Click here to receive $300,000 in cold hard cash!</h6></a>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const News: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-news"></div>
                        </div>
                    </div>
                     <div class="sb-card__main-content">
                        <a href=""><h3 class="">Shakespeare in the Park</h3></a>
                        <span class="meta">By <a href="">Jack Coles</a> — 2 days ago in </span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const Unit: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-unit"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">Romeo and Juliet</h6></a>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const User: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img">
                        <div class="img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h3 class="">Jana Reed</h3></a>
                        <span class="meta">Year 9 Senior Student</span>
                    </div>
                </div>
            </div>
        `,
    }),
};
