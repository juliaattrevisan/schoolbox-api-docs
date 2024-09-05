import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/layout/_gap.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/CSS/gap)
 *
 * Used with `.l-flex-*` and `.l-grid-*`, `gap` is the preferred method for adding space between elements.
 *
 * Compared to using `margin`, it allows the parent to control spacing. Have a read of [**this article**](https://markus.oberlehner.net/blog/css-the-spacing-between-elements-should-be-determined-by-the-parent-element/) if you'd like to know why this is preferred.
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
    title: "Layout/Gap",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.l-gap-xs`
 */
export const GapXS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-gap-xs">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.l-gap-s`
 */
export const GapS: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-gap-s">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.l-gap-m`
 */
export const GapM: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-gap-m">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.l-gap-l`
 */
export const GapL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-gap-l">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};

/**
 * `.l-gap-xl`
 */
export const GapXL: Story = {
    render: () => ({
        template: `
<div class="story-demo-boxes l-flex-row l-gap-xl">
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
    <div class="story-demo-box"></div>
</div>
        `,
    }),
};
