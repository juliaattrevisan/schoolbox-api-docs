import type { Meta, StoryObj } from "@storybook/vue3";
import type SbxLabelPastoralPoints from "@schoolboxVue/components/label/sbx-label-pastoral-points.ce.vue";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Source**: `vue/components/label/sbx-label-pastoral-points.ce.vue`
 *
 * To display a label
 *
 * ## Properties
 *
 * | Property | Type     | Description                                                                                      | Default        | Options                                                                   |
 * | -------- | -------- | ------------------------------------------------------------------------------------------------ | -------------- | ------------------------------------------------------------------------- |
 * | `points`   | `number` | The number of points | `0`           |                                       |
 */
const meta: Meta<typeof SbxLabelPastoralPoints> = {
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
    title: "Components/Labels/Pastoral Points Label",
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
            <sbx-label-pastoral-points points="100"></sbx-label-pastoral-points>
        `,
    }),
};

/**
 *
 */
export const Negative: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label-pastoral-points points="-50"></sbx-label-pastoral-points>
        `,
    }),
};
