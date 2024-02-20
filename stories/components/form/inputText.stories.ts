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
    title: "Draft/Form/Input Text",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-input-text`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text" type="text" required />
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
 * Has a validation error
 */
export const HasError: Story = {
    render: () => ({
        template: `
            <input id="news_default_expiry-field" class="c-input-text c-input-text--error" type="text" value="I'm invalid" />
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
            <div class="l-flex-col">
                <input id="news_default_expiry-field" class="c-input-text l-flex__item--1" type="text" />
                <button class="button l-width-100 l-mar--0">Browse Resources</button>
            </div>
            `,
    }),
};
