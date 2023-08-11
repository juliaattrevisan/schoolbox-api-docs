import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
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
    title: "Components/Cards/Default",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Standard card implementation
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img">
                        <div class="c-img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify l-flex__item--1 f-no-margins">
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                    <div class="c-card__main-actions">
                        <nav class="c-nav-actions l-flex-row">
                            <a class="icon-view no-text" href="" title="Hide"></a>
                            <a class="icon-modify no-text" href="" title="Modify"></a>
                            <a class="icon-favourite no-text" href="" title="Favourite"></a>
                        </nav>
                    </div>
                </div>
                <div class="l-pad-m">
                    <article>
                        <p>Come and see why Schoolbox is more than a school.&nbsp;&nbsp;You can find out more about the broad range of learning experiences offered - from academia,
                            internationalism and earth studies, to sensational music, theatre and sport.&nbsp; Talk to students, staff&nbsp;and parents to learn more at Open Day.&nbsp;&nbsp;<br><br>Caulfield
                            Grammar's three metropolitan campuses -&nbsp;Schoolbox&nbsp;Campus, Mars Campus and Venus Campus will conduct Open Days on:</p>
                        <blockquote>
                            <ul>
                                <li><strong>Saturday 17 August, 11am - 3pm</strong></li>
                            </ul>
                        </blockquote>
                        <p>Of course tours are available at any time. Please&nbsp;<a href="http://www.schoolbox.com.au" target="_self">contact us</a>&nbsp;to arrange an appointment.</p>
                    </article>
                </div>
                <div class="c-card__actions">
                    <a>READ MORE</a>
                    <a>ANOTHER ACTION</a>
                </div>
            </div>
        `,
    }),
};

export const ImageAndText: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img">
                        <div class="c-img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify f-no-margins">
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const IconAndText: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img l-pad-s">
                        <div class="c-img__wrapper">
                            <div class="icon icon-calendar"></div>
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify f-no-margins">
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const ImageAndTallText: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img">
                        <div class="c-img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify f-no-margins">
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Friday April 12th 2015 11:11am</span>
                        <span class="meta">Edited five days ago</span>
                        <span class="meta">Some more meta</span>
                        <span class="meta">And even more meta</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const CenteredImageAndTallText: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img l-flex--center">
                        <div class="c-img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify f-no-margins">
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Friday April 12th 2015 11:11am</span>
                        <span class="meta">Edited five days ago</span>
                        <span class="meta">Some more meta</span>
                        <span class="meta">And even more meta</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const ImageAndTextAndActions: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img">
                        <div class="c-img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify l-flex__item--1 f-no-margins">
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                    <div class="c-card__main-actions">
                        <nav class="c-nav-actions l-flex-row">
                            <a class="icon-view no-text" href="" title="Hide"></a>
                            <a class="icon-modify no-text" href="" title="Modify"></a>
                            <a class="icon-favourite no-text" href="" title="Favourite"></a>
                        </nav>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const CenteredImageAndTextAndActions: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img">
                        <div class="c-img__wrapper">
                            <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify l-flex__item--1 f-no-margins">
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                    <div class="c-card__main-actions l-flex--center">
                        <nav class="c-nav-actions l-flex-row">
                            <a class="icon-view no-text" href="" title="Hide"></a>
                            <a class="icon-modify no-text" href="" title="Modify"></a>
                            <a class="icon-favourite no-text" href="" title="Favourite"></a>
                        </nav>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const NavActions: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <nav class="c-nav-actions l-flex-row">
                    <a class="icon-modify no-text" href="" title="Modify"></a>
                    <a class="icon-modify no-text" href="" title="Modify"></a>
                    <a class="icon-more no-text" href="" title="Actions"></a>
                </nav>
            </div>
        `,
    }),
};

export const CardActions: Story = {
    render: () => ({
        template: `
            <div class="c-card">
                <div class="c-card__actions">
                    <a>READ MORE</a>
                    <a>ANOTHER ACTION</a>
                </div>
            </div>
        `,
    }),
};

/**
 * To be used when the card data is loading
 */
export const Loading: Story = {
    render: () => ({
        template: `
            <div class="c-card c-card--loading">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img">
                        <div class="c-img__wrapper">
                            <div class="img-placeholder"></div>
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify">
                        <h3></h3>
                        <span></span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const SubItem: Story = {
    render: () => ({
        template: `
            <div class="c-card c-card--subitem">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img c-img--s">
                        <div class="c-img__wrapper">
                            <img src="https://placedog.net/40/40" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify f-no-margins">
                        <a href=""><h6 class="">How cool is this dog?</h6></a>
                        <span class="meta">Last updated 3 weeks ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const SubItemWithIcon: Story = {
    render: () => ({
        template: `
            <div class="c-card c-card--subitem">
                <div class="l-flex-row l-gap-m">
                    <div class="c-img c-img--s">
                        <div class="c-img__wrapper">
                            <div class="icon icon-calendar"></div>
                        </div>
                    </div>
                    <div class="l-flex-col l-flex--center-justify f-no-margins">
                        <a href=""><h6 class="">How cool is this dog?</h6></a>
                        <span class="meta">Last updated 3 weeks ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const CardList: Story = {
    render: () => ({
        template: `
            <div class="l-flex-col">
                <div class="c-card c-card--border-end">
                    <div class="l-flex-row l-gap-m">
                        <div class="c-img l-pad-s">
                            <div class="c-img__wrapper">
                                <div class="icon icon-house"></div>
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--center-justify f-no-margins">
                            <a href=""><h3 class="">Year 12 Biology (BIO12)</h3></a>
                            <span class="meta"><a href="">Courses</a> > <a href="">Senior Courses</a> > <a href="">English</a> > <a href="">Year 12 Biology</a></span>
                        </div>
                    </div>
                </div>
                <div class="c-card c-card--subitem c-card--border-end">
                    <div class="l-flex-row l-gap-m">
                        <div class="c-img c-img--s">
                            <div class="c-img__wrapper">
                                <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--center-justify f-no-margins">
                            <a href=""><h6 class="">How cool is this dog?</h6></a>
                            <span class="meta">Last updated 1 week ago</span>
                        </div>
                    </div>
                </div>
                <div class="c-card c-card--subitem c-card--border-end">
                    <div class="l-flex-row l-gap-m">
                        <div class="c-img c-img--s">
                            <div class="c-img__wrapper">
                                <img src="https://placedog.net/96/96" alt="Placedog 64 by 64 image" />
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--center-justify f-no-margins">
                            <a href=""><h6 class="">What about this one?</h6></a>
                            <span class="meta">Last updated 2 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="c-card c-card--subitem">
                    <div class="l-flex-row l-gap-m">
                        <div class="c-img c-img--s">
                            <div class="c-img__wrapper">
                                <img src="https://placedog.net/108/108" alt="Placedog 64 by 64 image" />
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--center-justify f-no-margins">
                            <a href=""><h6 class="">Isn't this one cool too?</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
            </div>
        `,
    }),
};
