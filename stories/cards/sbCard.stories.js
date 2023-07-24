import StorybookSlot from "../../vue/components/storybook-slot";

export default {
    title: "Cards/SbCard",
    component: StorybookSlot,
};

const XSIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-xs">
                    <div class="img__wrapper">
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
                    <div class="img__wrapper">
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
                    <div class="img__wrapper">
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
                    <div class="img__wrapper">
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
                    <div class="img__wrapper">
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

const SubItemSIconTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="sb-card sb-card--subitem">
            <div class="sb-card__top">
                <div class="sb-card__primary-img img img-s">
                    <div class="img__wrapper">
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
                    <div class="img__wrapper">
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
