import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Cards",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Standard card implementation
 */
export const Default: Story = {
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
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                    <div class="sb-card__main-actions">
                        <nav class="sb-nav-actions">
                            <a class="icon-modify no-text" href="" title="Modify"></a>
                            <a class="icon-modify no-text" href="" title="Modify"></a>
                            <a class="icon-more no-text" href="" title="Actions"></a>
                        </nav>
                    </div>
                </div>
                <div class="sb-card__content">
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
                <div class="sb-card__actions">
                    <a>READ MORE</a>
                    <a>ANOTHER ACTION</a>
                </div>
            </div>
        `,
    }),
};

export const NavActions: Story = {
    render: () => ({
        template: `
            <div class="sb-card">
                <nav class="sb-nav-actions">
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
            <div class="sb-card">
                <div class="sb-card__actions">
                    <a>READ MORE</a>
                    <a>ANOTHER ACTION</a>
                </div>
            </div>
        `,
    }),
};

export const TopSection: Story = {
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
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const TopSectionMultiMeta: Story = {
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
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const TopSectionNavActionsVerticalAlign: Story = {
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
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                    <nav class="sb-nav-actions sb-flex--center">
                        <a class="icon-modify no-text" href="" title="Modify"></a>
                        <a class="icon-modify no-text" href="" title="Modify"></a>
                        <a class="icon-more no-text" href="" title="Actions"></a>
                    </nav>
                </div>
            </div>
        `,
    }),
};

export const TopSectionIcon: Story = {
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
                        <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                        <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const SubItem: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <img src="https://placedog.net/40/40" alt="Placedog 64 by 64 image" />
                        </div>
                    </div>
                    <div class="sb-card__main-content">
                        <a href=""><h6 class="">How cool is this dog?</h6></a>
                        <span class="meta">Last updated 3 weeks ago</span>
                    </div>
                </div>
            </div>
        `,
    }),
};

export const SubItemIcon: Story = {
    render: () => ({
        template: `
            <div class="sb-card sb-card--subitem">
                <div class="sb-card__main">
                    <div class="sb-card__primary-img img img-s">
                        <div class="img__wrapper">
                            <div class="icon icon-calendar"></div>
                        </div>
                    </div>
                    <div class="sb-card__main-content">
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
            <div class="sb-card-list">
                <div class="sb-card">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img">
                            <div class="img__wrapper">
                                <div class="icon icon-house"></div>
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h3 class="">Year 12 Biology (BIO12)</h3></a>
                            <span class="meta"><a href="">Courses</a> > <a href="">Senior Courses</a> > <a href="">English</a> > <a href="">Year 12 Biology</a></span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">How cool is this dog?</h6></a>
                            <span class="meta">Last updated 1 week ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://placedog.net/96/96" alt="Placedog 64 by 64 image" />
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">What about this one?</h6></a>
                            <span class="meta">Last updated 2 weeks ago</span>
                        </div>
                    </div>
                </div>
                <div class="sb-card sb-card--subitem">
                    <div class="sb-card__main">
                        <div class="sb-card__primary-img img img-s">
                            <div class="img__wrapper">
                                <img src="https://placedog.net/108/108" alt="Placedog 64 by 64 image" />
                            </div>
                        </div>
                        <div class="sb-card__main-content">
                            <a href=""><h6 class="">Isn't this one cool too?</h6></a>
                            <span class="meta">Last updated 3 weeks ago</span>
                        </div>
                    </div>
                </div>
            </div>
        `,
    }),
};
