import type { Meta, StoryObj } from "@storybook/vue3";
import type SbxLabel from "@schoolboxVue/components/label/sbx-label.ce.vue";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Source**: `vue/components/label/sbx-label.ce.vue`
 *
 * To display a label
 *
 * ## Properties
 *
 * | Property | Type     | Description                                                                                      | Default        | Options                                                                   |
 * | -------- | -------- | ------------------------------------------------------------------------------------------------ | -------------- | ------------------------------------------------------------------------- |
 * | `title`   | `string` | The title of the label | `""`           |                                       |
 * | `sentiment`   | `string` | The sentiment of the label, which impacts the color of the label                                                                          | `neutral`            | "success" | "warning" | "alert" | "neutral" |
 */
const meta: Meta<typeof SbxLabel> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="l-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Labels/Label",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 *
 */
export const Default: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label title="Label"></sbx-label>
        `,
    }),
};

/**
 *
 */
export const Success: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label title="Success" sentiment="success"></sbx-label>
        `,
    }),
};

/**
 *
 */
export const Warning: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label title="Warning" sentiment="warning"></sbx-label>
        `,
    }),
};

/**
 *
 */
export const Alert: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label title="Alert" sentiment="alert"></sbx-label>
        `,
    }),
};

/**
 *
 */
export const Neutral: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label title="Neutral" sentiment="neutral"></sbx-label>
        `,
    }),
};
