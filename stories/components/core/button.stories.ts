import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `www/static/css/core/ui/button/_c-button.scss`
 *
 * [mdn](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/button)
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="c-island">
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
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=222-6",
        },
    },
};

/**
 * Disabled buttons typically have poor contrast and can confuse some users, so avoid them if possible.
 */
export const Disabled: Story = {
    render: () => ({
        template: `
            <button class="c-button" type="button" disabled aria-disabled="true">Button</button>
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
            <a href="javascript:;" class="c-button">Button</a>
        `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=222-6",
        },
    },
};

/**
 * With an `<sbx-icon/>`
 */
export const WithIcon: Story = {
    render: () => ({
        template: `
<button type="button" class="c-button">
    <sbx-icon name="add"></sbx-icon>
    <span>Button</span>
</button>
        `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=222-29",
        },
    },
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
 *
 */
export const Secondary: Story = {
    render: () => ({
        template: `
<button type="button" class="c-button c-button--secondary">
    <span>Button</span>
</button>
        `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=222-49",
        },
    },
};

/**
 * Typically to identify the primary action on a page
 */
export const Success: Story = {
    render: () => ({
        template: `
<button type="button" class="c-button c-button--success">
    <span>Button</span>
</button>
        `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=222-89",
        },
    },
};

/**
 * To highlight that this action has a destructive effect
 */
export const Alert: Story = {
    render: () => ({
        template: `
<button type="button" class="c-button c-button--alert">
    <span>Button</span>
</button>
        `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=222-69",
        },
    },
};

/**
 * Just an icon button.
 * Ensure the `aria-label` attribute is set to describe the action
 */
export const IconOnly: Story = {
    render: () => ({
        template: `
<button type="button" class="c-button c-button--secondary c-button--icon-only" title="Add to favourites" aria-label="Add to favourites">
    <sbx-icon name="favourite"></sbx-icon>
</button>
        `,
    }),
};

export const ButtonGroup: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <button class="c-button" type="button">Button 1</button>
    <button class="c-button" type="button">Button 2</button>
    <button class="c-button" type="button">Button 3</button>
</div>
        `,
    }),
};

export const SecondaryButtonGroup: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <button class="c-button c-button--secondary" type="button">Button 1</button>
    <button class="c-button c-button--secondary" type="button">Button 2</button>
    <button class="c-button c-button--secondary" type="button">Button 3</button>
    <button class="c-button c-button--secondary" type="button">Button 4</button>
</div>
        `,
    }),
};

/**
 * A group of buttons stretched to fill the parent container
 * Should not typically contain more than 2 buttons, as the more buttons added, the more
 * likely the buttons will be too small on smaller devices
 */
export const ButtonGroupStretch: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-gap-s">
    <button class="c-button c-button--secondary l-flex__item--1" type="button">Secondary Action</button>
    <button class="c-button l-flex__item--1" type="button">Primary Action</button>
</div>
        `,
    }),
};
