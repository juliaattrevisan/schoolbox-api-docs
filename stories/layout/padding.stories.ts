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
    title: "Layout/Padding",
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
                <legend class="story-legend">.l-pad-xs</legend>
                <div class="story-demo-box l-pad-xs">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-s</legend>
                <div class="story-demo-box l-pad-s">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-m</legend>
                <div class="story-demo-box l-pad-m">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-l</legend>
                <div class="story-demo-box l-pad-l">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-xl</legend>
                <div class="story-demo-box l-pad-xl">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-m--horiz</legend>
                <div class="story-demo-box l-pad-m--horiz">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-m--vert</legend>
                <div class="story-demo-box l-pad-m--vert">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-m--top</legend>
                <div class="story-demo-box l-pad-m--top">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-m--bottom</legend>
                <div class="story-demo-box l-pad-m--bottom">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-m--left</legend>
                <div class="story-demo-box l-pad-m--left">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad-m--right</legend>
                <div class="story-demo-box l-pad-m--right">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset l-flex-row">
                <legend class="story-legend">.l-pad--0</legend>
                <div class="story-demo-box l-pad--0">
                    <div class="story-demo-box__inside"></div>
                </div>
            </fieldset>
        `,
    }),
};
