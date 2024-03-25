import type { Meta, StoryObj } from "@storybook/vue3";
import SamplePage from "./sample-page.vue";

/**
 * Sample form displaying all currently available form elements
 */
const meta: Meta<typeof SamplePage> = {
    component: SamplePage,
    title: "Draft/Form/Sample Page",
};
export default meta;
type Story = StoryObj<typeof SamplePage>;

export const Default: Story = {
    render: () => ({
        components: {
            SamplePage,
        },
        template: `
                <sample-page />
            `,
    }),
};
