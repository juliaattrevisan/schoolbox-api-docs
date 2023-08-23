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
    title: "Layout/Margin",
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
                <legend class="story-legend">.l-mar-xs</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-xs"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-s</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-s"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-m</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-m"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-l</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-l"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-xl</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-xl"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-m--horiz</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-m--horiz"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-m--vert</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-m--vert"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-m--top</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-m--top"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-m--bottom</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-m--bottom"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-m--left</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-m--left"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-mar-m--right</legend>
                <div class="story-demo-boxes l-flex-row">
                    <div class="story-demo-box l-mar-m--right"></div>
                </div>
            </fieldset>
        `,
    }),
};
