import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Layout/Guide",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * We just add the `c-card--small` modifier class to the card
 */
export const Default: Story = {
    render: () => ({
        template: `
            <p>Coming soon</p>
        `,
    }),
};
