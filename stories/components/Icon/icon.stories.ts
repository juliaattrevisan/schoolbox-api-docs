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
 * `<sbx-icon name="add"></sbx-icon>`
 */
export const Default: Story = {
    render: (args) => ({
        components: { SbxIcon },
        setup() {
            return { args };
        },
        template: `
            <sbx-icon v-bind="args" />
        `,
    }),
    args: {
        name: "add",
    },
};

/**
 * `<sbx-icon name="help" size="xl"></sbx-icon>`
 */
export const Size: Story = {
    render: (args) => ({
        components: { SbxIcon },
        setup() {
            return { args };
        },
        template: `
            <sbx-icon v-bind="args" />
        `,
    }),
    args: {
        name: "help",
        size: "xl",
    },
};

/**
 * `<sbx-icon name="danger" size="l" color="red"></sbx-icon>`
 */
export const Color: Story = {
    render: (args) => ({
        components: { SbxIcon },
        setup() {
            return { args };
        },
        template: `
            <sbx-icon v-bind="args" />
        `,
    }),
    args: {
        name: "danger",
        size: "100px",
        color: "red",
    },
};
