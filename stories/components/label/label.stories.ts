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
 * | Property    | Type     | Description                                                      | Default   | Options                                  |
 * | ----------- | -------- | ---------------------------------------------------------------- | --------- | ---------------------------------------- |
 * | `text`      | `string` | The text to display in the label                                | `""`      | |
 * | `title`     | `string` | The title to display when hovering over the label                | `""`      |
 * | `icon`      | `string` | The icon to display in the label                                | `""`      | Any icon name from the icon library      |
 * | `href`      | `string` | The URL to navigate to when the label is clicked                | `""`      |                                          |                                  |
 * | `sentiment` | `string` | The sentiment of the label, which impacts the color of the label | `"neutral"` | `"success"`, `"warning"`, `"alert"`, `"neutral"` |
 * | `backgroundColor` | `string` | To have total control of the background color of the label  | `undefined` | Any color allowed in the CSS spec |
 * | `textColor` | `string` | To have total control of the text color of the label  | `undefined` | Any color allowed in the CSS spec |
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
            <sbx-label text="Label"></sbx-label>
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
            <sbx-label text="Success" sentiment="success"></sbx-label>
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
            <sbx-label text="Warning" sentiment="warning"></sbx-label>
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
            <sbx-label text="Alert" sentiment="alert"></sbx-label>
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
            <sbx-label text="Neutral" sentiment="neutral"></sbx-label>
        `,
    }),
};

/**
 * We can also pass through a custom background color and text color to
 * completely override the default sentiment colors.
 */
export const CustomLabelColors: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label text="Label with custom colors" background-color="teal" text-color="#FFF"></sbx-label>
        `,
    }),
};

/**
 * We can pass through title and href to the label.
 * The title will be displayed when hovering over the label.
 * The href will be used as the link to navigate to when the label is clicked.
 * This is useful for labels that are used as links to other pages.
 */
export const WithHrefAndTitle: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label text="Link Label" href="#" sentiment="success"></sbx-label>
        `,
    }),
};

/**
 * We can pass through an icon to the label.
 * The icon will be displayed on the left of the label text.
 */
export const WithIcon: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label
                text="Label with Icon"
                title="Success with icon"
                icon="approve"
                sentiment="success"
            ></sbx-label>
        `,
    }),
};

export const WithHrefAndIcon: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label
                text="Clickable with Icon"
                title="Go to Schoolbox"
                href="#"
                icon="alert"
                sentiment="alert"
            ></sbx-label>
        `,
    }),
};

export const FullyCustom: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label
                text="Fully Custom"
                title="Styled, icon, and link"
                icon="info"
                href="#"
                background-color="#663399"
                text-color="#666666"
            ></sbx-label>
        `,
    }),
};
