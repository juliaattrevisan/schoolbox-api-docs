import type { Meta, StoryObj } from "@storybook/vue3";
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
    title: "Draft/Form/Input Checkbox",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
<div class="l-flex-row l-flex--align-center">
    <input id="cbx1" type="checkbox" class="c-input-checkbox" />
    <label for="cbx1" class="c-label l-pad-s--horiz">Checkbox</label>
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
<div class="l-flex-row l-flex--align-center">
    <input id="cbx2" type="checkbox" class="c-input-checkbox" disabled />
    <label for="cbx2" class="c-label l-pad-s--horiz">Checkbox</label>
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
<div class="l-flex-row l-flex--align-center">
    <input id="cbx2" type="checkbox" class="c-input-checkbox error"/>
    <label for="cbx2" class="c-label l-pad-s--horiz">Checkbox</label>
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
<div class="l-flex-row l-flex--align-center">
    <input id="cbx3" type="checkbox" class="c-input-checkbox c-input-checkbox--intermediate" />
    <label for="cbx3" class="c-label l-pad-s--horiz">Checkbox</label>
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
