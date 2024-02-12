import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
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
    title: "Draft/Button",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
            <button class="c-button" type="button">Button</button>
        `,
    }),
};

/**
 * Disabled buttons typically have poor contrast and can confuse some users, so avoid them if possible.
 */
export const Disabled: Story = {
    render: () => ({
        template: `
            <button class="c-button" type="button" disabled aria-disabled="true">Disabled Button</button>
        `,
    }),
};

/**
 * An `<a>` tag styled as a button. Historically we've used `<a>` tags where a `<button>` would have been more appropriate.
 * Moving forward we should use `<button>` tags for actions and `<a>` tags for navigation.
 */
export const LinkButton: Story = {
    render: () => ({
        template: `
            <a href="javascript:;" class="c-button">Button Link</a>
        `,
    }),
};

/**
 * With an `<sbx-icon/>`
 */
export const WithIcon: Story = {
    render: () => ({
        template: `
            <button type="button" class="c-button">
                <sbx-icon name="advanced-list"></sbx-icon>
                <span>Real Button</span>
            </button>
        `,
    }),
};

/**
 * To stretch full width of the parent container
 */
export const FullWidth: Story = {
    render: () => ({
        template: `
            <button type="button" class="c-button l-width-100">
                <span>Button</span>
            </button>
        `,
    }),
};

/**
 * Typically to identify the primary action on a page
 */
export const Primary: Story = {
    render: () => ({
        template: `
            <button type="button" class="c-button c-button--primary">
                <sbx-icon name="approve"></sbx-icon>
                <span>Save</span>
            </button>
        `,
    }),
};

/**
 * To highlight that this action has a destructive effect
 */
export const Alert: Story = {
    render: () => ({
        template: `
            <button type="button" class="c-button c-button--alert">
                <sbx-icon name="delete"></sbx-icon>
                <span>Delete</span>
            </button>
        `,
    }),
};

/**
 * Just an icon button.
 * Ensure the `aria-label` attribute is set to describe the action
 */
export const IconOnly: Story = {
    render: () => ({
        template: `
            <button type="button" class="c-button l-pad-s" aria-label="Add Curriculum">
                <sbx-icon name="favourite"></sbx-icon>
            </button>
        `,
    }),
};

/**
 * Rely on the background it sits on, typically used when the button is an icon only
 */
export const NoBackground: Story = {
    render: () => ({
        template: `
            <button type="button" class="c-button c-button--no-bkg l-pad-s">
                <sbx-icon name="add"></sbx-icon>
            </button>
        `,
    }),
};
