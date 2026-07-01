import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/utils/_u-margin.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/margin)
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
    title: "Utility/Margin",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.u-mar-xs`
 */
export const MarginXS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-xs"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-s`
 */
export const MarginS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-s"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-m`
 */
export const MarginM: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-l`
 */
export const MarginL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-l"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-xl`
 *
 */
export const MarginXL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-xl"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-m--horiz`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginHorizontals: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m--horiz"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-m--vert`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginVertical: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m--vert"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-m--top`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginTop: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m--top"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-m--bottom`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginBottom: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m--bottom"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-m--left`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginLeft: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m--left"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar-m--right`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginRight: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m--right"></div>
</div>
        `,
    }),
};

/**
 * `.u-mar--0`
 *
 * _Clear any existing margins on an element_
 */
export const Margin0: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row">
    <div class="story-demo-box u-mar-m u-mar--0"></div>
</div>
        `,
    }),
};
