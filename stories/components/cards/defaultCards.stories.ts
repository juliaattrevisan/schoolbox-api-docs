import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `stories/assets/styles/_card.scss`
 *
 * Styling rework of our card layouts, utilising flex to do a lot of the heavy lifting.
 *
 * As this is also flex based, we can leverage flex modifying classes like `l-flex--center` or `l-gap` without a problem.
 *
 * **Note**: The `c-new-card` class is used to avoid conflicts with the existing card styles.
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="l-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Draft/Card/Default",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Standard card implementation with all features displayed.
 */
export const Standard: Story = {
    render: () => ({
        template: `
<div class="c-new-card">
    <div class="l-flex-col l-gap-m l-pad-m">
        <div class="l-flex-row l-gap-m">
            <div class="c-img c-img--xl">
                <div class="c-img__wrapper">
                    <img src="/news.jpg" alt="Band playing music" />
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                <h3 class=""><a href="">School Musical Auditions & Rehearsals</a></h3>
                <span class="meta">By <a href="/search/user/6">Willow Tenorio</a> —<time title="Monday August 7th 2023 5:00am">1 week ago</time> in <a>Extra-Curricular</a></span>
            </div>
            <nav class="l-flex-row l-gap-s">
                <button type="button" class="c-button c-button--secondary c-button--icon-only" aria-label="Hide">
                    <sbx-icon name="view"></sbx-icon>
                </button>
                <button type="button" class="c-button c-button--secondary c-button--icon-only" aria-label="Modify">
                    <sbx-icon name="modify-pen"></sbx-icon>
                </button>
                <button type="button" class="c-button c-button--secondary c-button--icon-only" aria-label="Favourite">
                    <sbx-icon name="favourite"></sbx-icon>
                </button>
            </nav>
        </div>
        <article>
            <p>Come and see why Schoolbox is more than a school.&nbsp;&nbsp;You can find out more about the broad range of learning experiences offered - from academia,
                internationalism and earth studies, to sensational music, theatre and sport.&nbsp; Talk to students, staff&nbsp;and parents to learn more at Open Day.&nbsp;&nbsp;</p><p>Caulfield
                Grammar's three metropolitan campuses -&nbsp;Schoolbox&nbsp;Campus, Mars Campus and Venus Campus will conduct Open Days on:</p>
            <blockquote>
                <ul>
                    <li><strong>Saturday 17 August, 11am - 3pm</strong></li>
                </ul>
            </blockquote>
            <p>Of course tours are available at any time. Please&nbsp;<a href="http://www.schoolbox.com.au" target="_self">contact us</a>&nbsp;to arrange an appointment.</p>
        </article>
        <div class="l-flex-row l-flex--justify-end l-gap-s">
            <button class="c-button c-button--secondary" type="button">Button 1</button>
            <button class="c-button c-button--secondary" type="button">Button 2</button>
        </div>
    </div>
</div>
        `,
    }),
};

/**
 * Features can be removed for a more slimline card.
 *
 * Icons can also be used by replacing the `<img>` tag with a `<div class="icon icon-calendar"></div>`.
 */
export const Slimline: Story = {
    render: () => ({
        template: `
<div class="c-new-card">
    <div class="l-flex-row l-gap-m l-pad-m">
        <div class="c-img">
            <div class="c-img__wrapper">
                <sbx-icon name="calendar" size="2.5rem" color="var(--color-text-secondary)"></sbx-icon>
            </div>
        </div>
        <div class="l-flex-col l-flex--justify-center f-no-margins">
            <h3 class=""><a href="">Class Cancelled - 21st June</a></h3>
            <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
        </div>
    </div>
</div>
        `,
    }),
};

/**
 * The `l-flex--center` flex utlity class can be used to center the content of the card.
 */
export const SlimlineCentered: Story = {
    render: () => ({
        template: `
<div class="c-new-card">
    <div class="l-flex-row l-flex--align-center l-gap-m l-pad-m">
        <div class="c-img c-img--round">
            <div class="c-img__wrapper">
                <img src="/user.jpg" alt="Jana Reed profile photo" />
            </div>
        </div>
        <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
            <h3 class=""><a href="">Jana Reed</a></h3>
            <span class="meta">Friday April 12th 2015 11:11am</span>
            <span class="meta">Edited five days ago</span>
            <span class="meta">Some more meta</span>
            <span class="meta">And even more meta</span>
        </div>
        <nav class="c-nav-actions l-flex-row">
            <a class="icon-view no-text" href="" title="Hide"></a>
            <a class="icon-modify no-text" href="" title="Modify"></a>
            <a class="icon-favourite no-text" href="" title="Favourite"></a>
        </nav>
    </div>
</div>
        `,
    }),
};

/**
 * The `c-new-card__link` modifier class can be used when the card is a link.
 */
export const Link: Story = {
    render: () => ({
        template: `
<div class="c-new-card c-new-card--link">
    <div class="l-flex-row l-gap-m l-pad-m">
        <div class="c-img c-img--round">
            <div class="c-img__wrapper">
                <img src="https://schoolbox-static.cloud/23.0.5-1/images/badges/education/85.svg"/>
            </div>
        </div>
        <div class="l-flex-col l-flex--justify-center f-no-margins">
            <h3 class=""><a href="#first-link">Critical and Creative Thinking - Level 1</a></h3>
            <span class="meta">Learning</span>
        </div>
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
<div class="c-new-card c-new-card--loading">
    <div class="l-flex-row l-gap-m l-pad-m">
        <div class="c-img">
            <div class="c-img__wrapper">
                <div class="img-placeholder"></div>
            </div>
        </div>
        <div class="l-flex-col l-flex--justify-center l-flex__item--1 l-gap-m">
            <h3></h3>
            <span></span>
        </div>
    </div>
</div>
        `,
    }),
};

/**
 * The `c-new-card--child` modifier class is used to create a _child_ of a _parent_ card.
 */
export const Child: Story = {
    render: () => ({
        template: `
<div class="c-new-card c-new-card--child">
    <div class="l-flex-row l-gap-s l-pad-s">
        <div class="c-img c-img--s">
            <div class="c-img__wrapper">
                <div class="icon icon-unit"></div>
            </div>
        </div>
        <div class="l-flex-col l-flex--justify-center f-no-margins">
            <h6 class=""><a href="">Romeo and Juliet</a></h6>
        </div>
    </div>
</div>
        `,
    }),
};

/**
 * The `c-new-card--border-end` modifier class is used on each card except the last to create a border between cards.
 */
export const ParentWithChildren: Story = {
    render: () => ({
        template: `
<div class="l-flex-col">
    <div class="c-new-card c-new-card--border-end">
        <div class="l-flex-row l-gap-m l-pad-m">
            <div class="c-img">
                <div class="c-img__wrapper">
                    <div class="icon icon-house"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center f-no-margins">
                <h3 class=""><a href="">Year 9 English</a></h3>
                <span class="meta"><a href="">Courses</a> > <a href="">Senior Courses</a> > <a href="">English</a></span>
            </div>
        </div>
    </div>
    <div class="c-new-card c-new-card--child c-new-card--border-end">
        <div class="l-flex-row l-gap-s l-pad-s">
            <div class="c-img c-img--s">
                <div class="c-img__wrapper">
                    <div class="icon icon-unit"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center f-no-margins">
                <h6 class=""><a href="">Romeo and Juliet</a></h6>
            </div>
        </div>
    </div>
    <div class="c-new-card c-new-card--child c-new-card--border-end">
        <div class="l-flex-row l-gap-s l-pad-s">
            <div class="c-img c-img--s">
                <div class="c-img__wrapper">
                    <div class="icon icon-unit"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center f-no-margins">
                <h6 class=""><a href="">Persuasion and the Media</a></h6>
            </div>
        </div>
    </div>
    <div class="c-new-card c-new-card--child">
        <div class="l-flex-row l-gap-s l-pad-s">
            <div class="c-img c-img--s">
                <div class="c-img__wrapper">
                    <div class="icon icon-unit"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center f-no-margins">
                <h6 class=""><a href="">To Kill a Mockingbird</a></h6>
            </div>
        </div>
    </div>
</div>
        `,
    }),
};

/**
 * To help distinguish groups of cards `c-new-card--alt-bkg` can be used to add alternating backgrounds.
 */
export const Grouped: Story = {
    render: () => ({
        template: `
<div class="l-flex-col">
    <div class="c-new-card c-new-card--border-end">
        <div class="l-flex-row l-gap-m l-pad-m">
            <div class="c-img">
                <div class="c-img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                <h3 class=""><a href="">Class Cancelled - 21st June</a></h3>
                <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
            </div>
            <nav class="c-nav-actions l-flex-row">
                <a class="icon-view no-text" href="" title="Hide"></a>
                <a class="icon-modify no-text" href="" title="Modify"></a>
                <a class="icon-favourite no-text" href="" title="Favourite"></a>
            </nav>
        </div>
    </div>
    <div class="c-new-card c-new-card--alt-bkg c-new-card--border-end">
        <div class="l-flex-row l-gap-m l-pad-m">
            <div class="c-img">
                <div class="c-img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                <h3 class=""><a href="">Class Cancelled - 21st June</a></h3>
                <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
            </div>
            <nav class="c-nav-actions l-flex-row">
                <a class="icon-view no-text" href="" title="Hide"></a>
                <a class="icon-modify no-text" href="" title="Modify"></a>
                <a class="icon-favourite no-text" href="" title="Favourite"></a>
            </nav>
        </div>
    </div>
    <div class="c-new-card c-new-card--border-end">
        <div class="l-flex-row l-gap-m l-pad-m">
            <div class="c-img">
                <div class="c-img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                <h3 class=""><a href="">Class Cancelled - 21st June</a></h3>
                <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
            </div>
            <nav class="c-nav-actions l-flex-row">
                <a class="icon-view no-text" href="" title="Hide"></a>
                <a class="icon-modify no-text" href="" title="Modify"></a>
                <a class="icon-favourite no-text" href="" title="Favourite"></a>
            </nav>
        </div>
    </div>
    <div class="c-new-card c-new-card--alt-bkg c-new-card--border-end">
        <div class="l-flex-row l-gap-m l-pad-m">
            <div class="c-img">
                <div class="c-img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                <h3 class=""><a href="">Class Cancelled - 21st June</a></h3>
                <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
            </div>
            <nav class="c-nav-actions l-flex-row">
                <a class="icon-view no-text" href="" title="Hide"></a>
                <a class="icon-modify no-text" href="" title="Modify"></a>
                <a class="icon-favourite no-text" href="" title="Favourite"></a>
            </nav>
        </div>
    </div>
    <div class="c-new-card">
        <div class="l-flex-row l-gap-m l-pad-m">
            <div class="c-img">
                <div class="c-img__wrapper">
                    <div class="icon icon-calendar"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                <h3 class=""><a href="">Class Cancelled - 21st June</a></h3>
                <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
            </div>
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
