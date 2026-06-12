import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import type SbxIcon from "@schoolboxVue/components/icon/sbx-icon.ce.vue";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * - Defined in: `vue/components/icon/sbx-icon.ce.vue`.
 * - To be used when displaying icons.
 *
 * ## Properties
 *
 * | Property | Type     | Description                                                                                      | Default        | Options                                                                   |
 * | -------- | -------- | ------------------------------------------------------------------------------------------------ | -------------- | ------------------------------------------------------------------------- |
 * | `color`  | `string` | The color of the icon.                                                                           | `currentColor` | Can accept any valid CSS colour value, including custom properties.       |
 * | `name`   | `string` | The name of the icon, as specified [here](?path=/docs/icons-icons--docs)                         | `""`           | Any string name linked to an icon                                         |
 * | `size`   | `string` | The size of the icon                                                                             | `m`            | `xs`, `s`, `m`, `l`, `xl` `xxl`, or any other appropriate size value like `36px` |
 */
const meta: Meta<typeof SbxIcon> = {
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
 * Changing the size.
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
 * Changing the color.
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
 * Any `<svg>` can be passed in within the `<sbx-icon>` component.
 */
export const Slot: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
<sbx-icon size="100px">
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
