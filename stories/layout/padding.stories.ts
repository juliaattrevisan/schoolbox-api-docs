import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_padding.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/padding)
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="l-flex-row l-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Layout/Padding",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.l-pad-xs`
 */
export const PaddingXS: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-xs">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-s`
 */
export const PaddingS: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-s">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-m`
 */
export const PaddingM: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-l`
 */
export const PaddingL: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-l">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-xl`
 */
export const PaddingXL: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-xl">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-m--horiz`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingHorizontal: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m--horiz">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-m--vert`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingVertical: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m--vert">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-m--top`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingTop: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m--top">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-m--bottom`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingBottom: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m--bottom">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-m--left`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingLeft: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m--left">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad-m--right`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingRight: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m--right">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.l-pad--0`
 *
 * _Clear any existing padding on an element_
 */
export const Padding0: Story = {
    render: () => ({
        template: `
<div class="story-demo-box l-pad-m l-pad--0">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};
