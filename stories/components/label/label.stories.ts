import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import type SbxLabel from "@schoolboxVue/components/label/sbx-label.ce.vue";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * - Defined in: `vue/components/label/sbx-label.ce.vue`.
 * - Dependencies: [Icon](?path=/docs/components-icon--docs).
 * - To be used when displaying labels.
 *
 * ## Properties
 *
 * | Property    | Type     | Description                                                      | Default   | Options                                  |
 * | ----------- | -------- | ---------------------------------------------------------------- | --------- | ---------------------------------------- |
 * | `backgroundColor` | `string` | To have total control of the background color of the label  | `undefined` | Any color allowed in the CSS spec |
 * | `href`      | `string` | The URL to navigate to when the label is clicked                | `""`      |                                          |
 * | `icon`      | `string` | The icon to display in the label                                | `""`      | Any icon name from the icon library      |
 * | `isButton`  | `boolean` | Can be used as a button                                         | `false`   | `true` or `false`                        |
 * | `isFullWidth` | `boolean` | Whether the label should fill the available width             | `false`   | `true` or `false`                        |
 * | `sentiment` | `string` | The sentiment of the label, which impacts the color of the label | `"neutral"` | `"success"`, `"warning"`, `"alert"`, `"neutral"` |
 * | `size`      | `string` | The size of the label                                           | `"m"`     | `"m"`, `"l"` |
 * | `text`      | `string` | The text to display in the label                                | `""`      | |
 * | `textColor` | `string` | To have total control of the text color of the label  | `undefined` | Any color allowed in the CSS spec |
 * | `title`     | `string` | The title to display when hovering over the label                | `""`      | |
 */
const meta: Meta<typeof SbxLabel> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="u-pad-m">
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
 * The label can be displayed in a larger size by setting `size` to `l`.
 */
export const LargeSize: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label text="Large Label" size="l"></sbx-label>
        `,
    }),
};

/**
 * The label can fill its available width by adding the `is-full-width` property.
 */
export const FullWidth: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label text="Full width label" is-full-width></sbx-label>
        `,
    }),
};

/**
 * When the label is to express a positive sentiment, such as success or completion of a task.
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
 * When the label is to express a warning sentiment, such as caution or potential issues.
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
 * When the label is to express an alert sentiment, such as an error or critical issue.
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
 * When the label is to express a neutral sentiment, such as general information or a default state.
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
 * The label can be used as a button by adding the `is-button` property.
 */
export const AsButton: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
            <sbx-label text="Click me" is-button></sbx-label>
        `,
    }),
};

/**
 * A custom background color and text color can also completely override the default sentiment colors.
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
 * Adding an icon to the label.
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

/**
 * We can also combine the href and icon properties to have a label that is both clickable and has an icon.
 */
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

/**
 * By using the `backgroundColor` and `textColor` properties, we can have full control over the colours of the label, while still using the other features such as icons and links.
 */
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
                text-color="#e0e0e0"
            ></sbx-label>
        `,
    }),
};
