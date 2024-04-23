import type { Meta, StoryObj } from "@storybook/vue3";
import SkinConfig from "./skin-config.vue";

/**
 * Prototype showing new custom properties and their use in a new skin config page
 */
const meta: Meta<typeof SkinConfig> = {
    component: SkinConfig,
    title: "Draft/Skin Config",
};
export default meta;
type Story = StoryObj<typeof SkinConfig>;

export const Default: Story = {
    render: () => ({
        components: {
            SkinConfig,
        },
        template: `
                <skin-config />
            `,
    }),
};
