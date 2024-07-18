import type { Meta, StoryObj } from "@storybook/vue3";
import BsModal from "../../../vue/components/birthday/bs-modal.vue";

/**
 * Animation that appears within the birthday modal when the birthday notification is selected.
 */
const meta: Meta<typeof BsModal> = {
    component: BsModal,
    title: "Animations/Birthday/Birthday Modal",
};
export default meta;
type Story = StoryObj<typeof BsModal>;

export const Default: Story = {
    render: () => ({
        components: {
            BsModal,
        },
        template: `
                <bs-modal />
            `,
    }),
};
