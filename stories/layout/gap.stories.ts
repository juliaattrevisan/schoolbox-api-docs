import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_gap.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/gap)
 *
 * Used with `.u-flex-*` and `.l-grid-*`, `gap` is the preferred method for adding space between elements.
 *
 * Compared to using `margin`, it allows the parent to control spacing. Have a read of [**this article**](https://markus.oberlehner.net/blog/css-the-spacing-between-elements-should-be-determined-by-the-parent-element/) if you'd like to know why this is preferred.
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="u-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Layout/Gap",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.u-gap-xs`
 */
export const GapXS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-gap-xs">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.u-gap-s`
 */
export const GapS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-gap-s">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.u-gap-m`
 */
export const GapM: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-gap-m">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.u-gap-l`
 */
export const GapL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-gap-l">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.u-gap-xl`
 */
export const GapXL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes u-flex-row u-gap-xl">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};
