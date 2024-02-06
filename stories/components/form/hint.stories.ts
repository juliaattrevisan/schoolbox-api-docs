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
    title: "Components/Form/Hint",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * `.c-hint`
 */
export const Default: Story = {
    render: () => ({
        template: `
            <div class="c-hint">Insert the default length of time from when news is published to when it is archived.</div>
            `,
    }),
};
