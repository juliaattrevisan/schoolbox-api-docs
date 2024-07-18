import type { Meta, StoryObj } from "@storybook/vue3";
import BsNotification from "../../../vue/components/birthday/bs-notification.vue";

/**
 * Animation that appears over the notification bell when it is the user's birthday.
 * Selecting the notification will display the birthday modal.
 */
const meta: Meta<typeof BsNotification> = {
    component: BsNotification,
    title: "Animations/Birthday/Birthday Notification",
};
export default meta;
type Story = StoryObj<typeof BsNotification>;

export const Default: Story = {
    render: () => ({
        components: {
            BsNotification,
        },
        template: `
                <bs-notification />
            `,
    }),
};
