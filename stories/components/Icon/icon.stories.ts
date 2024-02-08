import type { Meta, StoryObj } from "@storybook/vue3";
import SbxIcon from "@schoolboxVue/components/icon/sbx-icon.ce.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof SbxIcon> = {
    component: SbxIcon,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Icon",
};
export default meta;
type Story = StoryObj<typeof SbxIcon>;

/**
 *
 */
export const Default: Story = {
    render: () => ({
        components: { SbxIcon },
        template: `
            <sbx-icon name="add"></sbx-icon>
        `,
    }),
};

/**
 *
 */
export const Size: Story = {
    render: () => ({
        components: { SbxIcon },
        template: `
            <sbx-icon name="approve" size="xl"></sbx-icon>
        `,
    }),
};

/**
 *
 */
export const Color: Story = {
    render: () => ({
        components: { SbxIcon },
        template: `
            <sbx-icon name="close" color="red"></sbx-icon>
        `,
    }),
};
