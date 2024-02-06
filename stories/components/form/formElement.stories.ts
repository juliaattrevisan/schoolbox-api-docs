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
    title: "Components/Form/Form Element",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-form-element`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-form-element">
                <label for="news_default_expiry-field" class="c-label">Default News Archiving Period</label>
                <input id="news_default_expiry-field" class="c-input-text" type="text" />
            </div>
            `,
    }),
};

/**
 * With a hint added
 */
export const WithHint: Story = {
    render: () => ({
        template: `
            <div class="c-form-element">
                <label for="news_default_expiry-field" class="c-label">Default News Archiving Period</label>
                <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
                <input id="news_default_expiry-field" class="c-input-text" type="text" />
            </div>
            `,
    }),
};

/**
 * With an error message
 */
export const WithError: Story = {
    render: () => ({
        template: `
            <div class="c-form-element">
                <label for="news_default_expiry-field" class="c-label c-label--required">Default News Archiving Period</label>
                <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
                <input id="news_default_expiry-field" class="c-input-text" type="text" required />
                <label id="news-title-error" class="error" for="news-title">This field is required.</label>
            </div>
            `,
    }),
};
