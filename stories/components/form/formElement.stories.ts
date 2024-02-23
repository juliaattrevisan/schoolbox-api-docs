import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";
import { List } from "./inputCheckbox.stories";

/**
 * A single form element, such as a label and input, or a label and textarea.
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
    title: "Draft/Form/Form Element",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 *
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="l-flex-col l-gap-s">
                <label for="input1" class="c-label">Default News Archiving Period</label>
                <input id="input1" class="c-input-text" type="text" />
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
            <div class="l-flex-col l-gap-s">
                <label for="input2" class="c-label">Default News Archiving Period</label>
                <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
                <input id="input2" class="c-input-text" type="text" />
            </div>
            `,
    }),
};

/**
 *
 */
export const CheckboxList: Story = {
    ...(List as StoryObj),
};

/**
 * With an error message
 */
export const WithError: Story = {
    render: () => ({
        template: `
            <div class="l-flex-col l-gap-s">
                <label for="input3" class="c-label c-label--required">Default News Archiving Period</label>
                <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
                <input id="input3" class="c-input-text c-input-text--error" type="text" required />
                <label id="news-title-error" class="error" for="news-title">This field is required.</label>
            </div>
            `,
    }),
};
