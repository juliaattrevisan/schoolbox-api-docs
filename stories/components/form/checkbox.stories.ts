import type { Meta, StoryObj } from "storybook-vue3-rsbuild";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `www/static/css/core/ui/forms/_input-checkbox.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/checkbox)
 *
 * **Usage notes**:
 * - Must always be accompanied by a `<label>` element.
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
    title: "Draft/Form/Checkbox",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
<div class="sbx-checkbox-radio-container">
    <input id="cbx1" type="checkbox" />
    <label for="cbx1">Checkbox</label>
</div>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=378-175&t=k3aLa3OixG4ZXmYW-4",
        },
    },
};

export const Disabled: Story = {
    render: () => ({
        template: `
<div class="sbx-checkbox-radio-container">
    <input id="cbx2" type="checkbox" disabled />
    <label for="cbx2">Checkbox</label>
</div>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=387-52&t=pOW7vFLg8WXZIutZ-4",
        },
    },
};

export const Error: Story = {
    render: () => ({
        template: `
<div class="sbx-checkbox-radio-container">
    <input id="cbx2" type="checkbox" class="error"/>
    <label for="cbx2">Checkbox</label>
</div>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=387-64&t=pOW7vFLg8WXZIutZ-4",
        },
    },
};

/**
 * Intermediate state, to show some children are checked and some are not
 */
export const Intermediate: Story = {
    render: () => ({
        template: `
<div class="sbx-checkbox-radio-container">
    <input id="cbx3" type="checkbox" class="intermediate" />
    <label for="cbx3">Checkbox</label>
</div>
            `,
    }),
    parameters: {
        design: {
            type: "figma",
            url: "https://www.figma.com/design/kcebwjgNDzqyTtXZzgQNMO/UI-Kit?node-id=387-84&t=pOW7vFLg8WXZIutZ-4",
        },
    },
};
