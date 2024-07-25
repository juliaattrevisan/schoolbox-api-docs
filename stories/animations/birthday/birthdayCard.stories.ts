import type { Meta, StoryObj } from "@storybook/vue3";
import BsCard from "../../../vue/components/birthday/bs-card.vue";

/**
 * Animation that appears within the modal when the birthday notification is selected.
 */
const meta: Meta<typeof BsCard> = {
    component: BsCard,
    title: "Animations/Birthday/Birthday Card",
};
export default meta;
type Story = StoryObj<typeof BsCard>;

export const Default: Story = {
    render: () => ({
        components: {
            BsCard,
        },
        template: `
                <bs-card />
            `,
    }),
};
