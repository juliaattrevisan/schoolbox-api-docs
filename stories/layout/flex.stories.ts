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
    title: "Layout/Flex",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 *
 */
export const Classes: Story = {
    render: () => ({
        template: `
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex-row</legend>
                <div class="story-demo-boxes l-flex-row l-gap-m">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>4</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>5</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>6</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>7</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>8</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>9</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex-col</legend>
                <div class="story-demo-boxes l-flex-col l-gap-m">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex--wrap-reverse</legend>
                <div class="story-demo-boxes l-flex-row l-flex--wrap-reverse l-gap-m">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>4</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>5</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>6</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>7</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>8</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>9</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex--center</legend>
                <div class="story-demo-boxes l-flex-row l-flex--center l-gap-m" style="height: 200px;">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex--align-center</legend>
                <div class="story-demo-boxes l-flex-row l-flex--align-center l-gap-m" style="height: 200px;">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex--justify-center</legend>
                <div class="story-demo-boxes l-flex-row l-flex--justify-center l-gap-m">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex--justify-end</legend>
                <div class="story-demo-boxes l-flex-row l-flex--justify-end l-gap-m">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex--justify-space</legend>
                <div class="story-demo-boxes l-flex-row l-flex--justify-space l-gap-m">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                </div>
            </fieldset>
            <fieldset class="story-fieldset">
                <legend class="story-legend">.l-flex__item--1</legend>
                <div class="story-demo-boxes l-flex-row l-gap-m">
                    <div class="story-demo-box l-flex l-flex--center"><span>1</span></div>
                    <div class="story-demo-box l-flex l-flex--center l-flex__item--1"><span>2</span></div>
                    <div class="story-demo-box l-flex l-flex--center"><span>3</span></div>
                </div>
            </fieldset>
        `,
    }),
};
