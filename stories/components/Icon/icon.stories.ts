import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof SbxIcon> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-m">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Icon",
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
            <sbx-icon name="add"></sbx-icon>
        `,
    }),
};

/**
 * Changing the size
 */
export const Size: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-icon name="help" size="xl"></sbx-icon>
        `,
    }),
};

/**
 * Changing the color
 */
export const Color: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-icon name="danger" size="200px" color="red"></sbx-icon>
        `,
    }),
};

/**
 * Any `<svg>` can be passed in within the `<sbx-icon>` component
 */
export const Slot: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-icon name="" size="100px">
                <svg viewBox="0 0 200 200" width="400"  height="400" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="100" cy="100" fill="yellow" r="78" stroke="black" stroke-width="3"/>
                    <g class="eyes">
                        <circle cx="61" cy="82" r="12"/>
                        <circle cx="127" cy="82" r="12"/>
                    </g>
                    <path d="m136.81 116.53c.69 26.17-64.11 42-81.52-.73" style="fill:none; stroke: black; stroke-width: 3;"/>
                </svg>
            </sbx-icon>
        `,
    }),
};
