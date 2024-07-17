import type { Meta, StoryObj } from "@storybook/vue3";
import BirthdayNotification from "./birthday-notification.vue";

/**
 * Animation that appears over the notification bell when it is the user's birthday.
 * Selecting the notification will display the birthday modal.
 */
const meta: Meta<typeof BirthdayNotification> = {
    component: BirthdayNotification,
    title: "Animations/Birthday/Birthday Notification",
};
export default meta;
type Story = StoryObj<typeof BirthdayNotification>;

export const Default: Story = {
    render: () => ({
        components: {
            BirthdayNotification,
        },
        template: `
                <birthday-notification />
            `,
    }),
};
