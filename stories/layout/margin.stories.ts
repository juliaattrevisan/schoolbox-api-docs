import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_margin.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/margin)
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
    title: "Layout/Margin",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.l-mar-xs`
 */
export const MarginXS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-xs"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-s`
 */
export const MarginS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-s"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-m`
 */
export const MarginM: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-l`
 */
export const MarginL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-l"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-xl`
 *
 */
export const MarginXL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-xl"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-m--horiz`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginHorizontals: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m--horiz"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-m--vert`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginVertical: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m--vert"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-m--top`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginTop: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m--top"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-m--bottom`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginBottom: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m--bottom"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-m--left`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginLeft: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m--left"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar-m--right`
 *
 * _Can be used with any of the sizes above_
 */
export const MarginRight: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m--right"></div>
</div>
        `,
    }),
};

/**
 * `.l-mar--0`
 *
 * _Clear any existing margins on an element_
 */
export const Margin0: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row">
    <div class="story-demo-box l-mar-m l-mar--0"></div>
</div>
        `,
    }),
};
