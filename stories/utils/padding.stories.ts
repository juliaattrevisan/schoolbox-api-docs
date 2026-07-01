import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/utils/_u-padding.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/padding)
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="u-flex-row u-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Utility/Padding",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.u-pad-xs`
 */
export const PaddingXS: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-xs">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-s`
 */
export const PaddingS: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-s">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-m`
 */
export const PaddingM: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-l`
 */
export const PaddingL: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-l">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-xl`
 */
export const PaddingXL: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-xl">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-m--horiz`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingHorizontal: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m--horiz">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-m--vert`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingVertical: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m--vert">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-m--top`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingTop: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m--top">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-m--bottom`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingBottom: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m--bottom">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-m--left`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingLeft: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m--left">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad-m--right`
 *
 * _Can be used with any of the sizes above_
 */
export const PaddingRight: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m--right">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};

/**
 * `.u-pad--0`
 *
 * _Clear any existing padding on an element_
 */
export const Padding0: Story = {
    render: () => ({
        template: `
<div class="story-demo-box u-pad-m u-pad--0">
    <div class="story-demo-box__inside"></div>
</div>
        `,
    }),
};
