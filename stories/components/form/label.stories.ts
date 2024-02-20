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
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Draft/Form/Label",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-label`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <label class="c-label">Title</label>
            `,
    }),
};

/**
 * Adding `.c-label--required`
 */
export const Required: Story = {
    render: () => ({
        template: `
            <label class="c-label c-label--required">Title</label>
            `,
    }),
};
