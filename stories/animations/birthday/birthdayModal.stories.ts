import type { Meta, StoryObj } from "@storybook/vue3";
import BirthdayModal from "./birthday-modal.vue";

/**
 * Animation that appears within the birthday modal when the birthday notification is selected.
 */
const meta: Meta<typeof BirthdayModal> = {
    component: BirthdayModal,
    title: "Animations/Birthday/Birthday Modal",
};
export default meta;
type Story = StoryObj<typeof BirthdayModal>;

export const Default: Story = {
    render: () => ({
        components: {
            BirthdayModal,
        },
        template: `
                <birthday-modal />
            `,
    }),
};
