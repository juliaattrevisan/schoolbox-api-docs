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
 *
 */
export const Classes: Story = {
    render: () => ({
        template: `
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-gap-xs</legend>
                <div class="story-demo-boxes l-flex-row l-gap-xs">
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-gap-s</legend>
                <div class="story-demo-boxes l-flex-row l-gap-s">
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-gap-m</legend>
                <div class="story-demo-boxes l-flex-row l-gap-m">
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-gap-l</legend>
                <div class="story-demo-boxes l-flex-row l-gap-l">
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-gap-xl</legend>
                <div class="story-demo-boxes l-flex-row l-gap-xl">
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                    <div class="story-demo-box"></div>
                </div>
            </fieldset>
        `,
    }),
};
