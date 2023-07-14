import StorybookSlot from "../../vue/components/storybook-slot";

export default {
    title: "Cards/SbCard",
    component: StorybookSlot,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img">
                    <div class="img__affector">
                        <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                    <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                </div>
                <div class="sb-card__top-actions">
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
});

const NavActionsTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <nav class="sb-nav-actions">
                <a class="icon-modify no-text" href="" title="Modify"></a>
                <a class="icon-modify no-text" href="" title="Modify"></a>
                <a class="icon-more no-text" href="" title="Actions"></a>
            </nav>
        </div>
    `,
});

const DummyTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `

    `,
});

const CardActionsTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__actions">
                <a>READ MORE</a>
                <a>ANOTHER ACTION</a>
            </div>
        </div>
    `,
});

const TopSectionTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img">
                    <div class="img__affector">
                        <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                    <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                </div>
            </div>
        </div>
    `,
});

const TopSectionMultiMetaTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img">
                    <div class="img__affector">
                        <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
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
});

const TopSectionNavActionsVerticalAlignTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img">
                    <div class="img__affector">
                        <img src="https://placedog.net/64/64" alt="Placedog 64 by 64 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
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
});

const TopSectionIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                    <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                </div>
            </div>
        </div>
    `,
});

const RoundedImageTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-m img--round">
                    <div class="img__affector">
                        <img src="https://placedog.net/100/200" alt="Placedog 100 by 200 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">Class Cancelled - 21st June</h3></a>
                    <span class="meta">Posted by <a href="">Ms. Celia Glenn</a> 2 days ago</span>
                </div>
            </div>
        </div>
    `,
});

const XSImageTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-xs">
                    <div class="img__affector">
                        <img src="https://placedog.net/200/100" alt="Placedog 200 by 100 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6>This is some text here</h6></a>
                </div>
            </div>
        </div>
    `,
});

const SImageTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-s">
                    <div class="img__affector">
                        <img src="https://placedog.net/200/100" alt="Placedog 200 by 100 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6>This is some text here</h6></a>
                </div>
            </div>
        </div>
    `,
});

const MImageTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-m">
                    <div class="img__affector">
                        <img src="https://placedog.net/200/100" alt="Placedog 200 by 100 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6>This is some text here</h6></a>
                </div>
            </div>
        </div>
    `,
});

const LImageTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-l">
                    <div class="img__affector">
                        <img src="https://placedog.net/200/100" alt="Placedog 200 by 100 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6>This is some text here</h6></a>
                </div>
            </div>
        </div>
    `,
});

const XLImageTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-xl">
                    <div class="img__affector">
                        <img src="https://placedog.net/200/100" alt="Placedog 200 by 100 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6>This is some text here</h6></a>
                </div>
            </div>
        </div>
    `,
});

const XSIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-xs">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">This is some text here</h3></a>
                </div>
            </div>
        </div>
    `,
});

const SIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-s">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">This is some text here</h3></a>
                </div>
            </div>
        </div>
    `,
});

const MIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-m">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">This is some text here</h3></a>
                </div>
            </div>
        </div>
    `,
});

const LIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-l">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">What is updog?</h3></a>
                </div>
            </div>
        </div>
    `,
});

const XLIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-xl">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h3 class="">This is some text here</h3></a>
                </div>
            </div>
        </div>
    `,
});

const SubItemTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card sb-card--subitem">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img">
                    <div class="img__affector">
                        <img src="https://placedog.net/40/40" alt="Placedog 64 by 64 image" />
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6 class="">How cool is this dog?</h6></a>
                    <span class="meta">Last updated 3 weeks ago</span>
                </div>
            </div>
        </div>
    `,
});

const SubItemIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card sb-card--subitem">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6 class="">How cool is this dog?</h6></a>
                    <span class="meta">Last updated 3 weeks ago</span>
                </div>
            </div>
        </div>
    `,
});

const SubItemSIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card sb-card--subitem">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-s">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6 class="">How cool is this dog?</h6></a>
                    <span class="meta">Last updated 3 weeks ago</span>
                </div>
            </div>
        </div>
    `,
});

const SubItemLIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card sb-card--subitem">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-l">
                    <div class="img__affector">
                        <div class="icon icon-calendar"></div>
                    </div>
                </div>
                <div class="sb-card__top-content">
                    <a href=""><h6 class="">How cool is this dog?</h6></a>
                    <span class="meta">Last updated 3 weeks ago</span>
                </div>
            </div>
        </div>
    `,
});

const CardListTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card-list">

        </div>
    `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const NavActions = NavActionsTemplate.bind({});
NavActions.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const CardActions = CardActionsTemplate.bind({});
CardActions.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const TopSection = TopSectionTemplate.bind({});
TopSection.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const TopSectionMultiMeta = TopSectionMultiMetaTemplate.bind({});
TopSectionMultiMeta.parameters = {
    controls: { hideControlsWarning: true },
};

export const TopSectionNavActionsVerticalAlign = TopSectionNavActionsVerticalAlignTemplate.bind({});
TopSectionNavActionsVerticalAlign.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const TopSectionIcon = TopSectionIconTemplate.bind({});
TopSectionIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const RoundedImage = RoundedImageTemplate.bind({});
RoundedImage.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const XSImage = XSImageTemplate.bind({});
XSImage.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const SImage = SImageTemplate.bind({});
SImage.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const MImage = MImageTemplate.bind({});
MImage.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const LImage = LImageTemplate.bind({});
LImage.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const XLImage = XLImageTemplate.bind({});
XLImage.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const XSIcon = XSIconTemplate.bind({});
XSIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const SIcon = SIconTemplate.bind({});
SIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const MIcon = MIconTemplate.bind({});
MIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const LIcon = LIconTemplate.bind({});
LIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const XLIcon = XLIconTemplate.bind({});
XLIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const SubItem = SubItemTemplate.bind({});
SubItem.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const SubItemIcon = SubItemIconTemplate.bind({});
SubItemIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const SubItemSIcon = SubItemSIconTemplate.bind({});
SubItemSIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const SubItemLIcon = SubItemLIconTemplate.bind({});
SubItemLIcon.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const CardList = CardListTemplate.bind({});
CardList.parameters = {
    controls: { hideNoControlsWarning: true },
};
