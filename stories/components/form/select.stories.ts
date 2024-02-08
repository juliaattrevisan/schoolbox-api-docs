import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="c-island">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Draft/Form/Select",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-input-text`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-select">
                <select id="standard-select">
                    <option value="Option 1">Option 1</option>
                    <option value="Option 2">Option 2</option>
                    <option value="Option 3">Option 3</option>
                    <option value="Option 4">Option 4</option>
                    <option value="Option 5">Option 5</option>
                    <option value="Option length">
                    Option that has too long of a value to fit
                    </option>
                </select>
            </div>
            `,
    }),
};

/**
 * Input disabled
 */
export const Disabled: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm disabled" disabled />
            `,
    }),
};

/**
 * Input read only
 */
export const ReadOnly: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" value="I'm read only" readonly />
            `,
    }),
};

/**
 * With a button, typically when an action following the button selection fills the input
 * For example a date picker or resource browser
 */
export const WithButton: Story = {
    render: () => ({
        template: `
            <div class="l-flex l-flex--col">
                <input id="news_default_expiry-field" class="c-input-text l-flex__item--1" type="text" />
                <button class="button l-width-100 l-mar--0">Browse Resources</button>
            </div>
            `,
    }),
};
