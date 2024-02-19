import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-xl">
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
