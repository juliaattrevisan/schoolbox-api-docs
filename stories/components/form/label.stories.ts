import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `www/static/css/core/ui/forms/_label.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/label)
 *
 * The `<label>` HTML element represents a caption for an item in a user interface.
 *
 * **Usage notes**:
 * - Ensure `for="id"` attribute matches the `id` of the input it is labelling.
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Draft/Form/Label",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
            <label class="c-label">Label</label>
            `,
    }),
};

export const Required: Story = {
    render: () => ({
        template: `
            <label class="c-label c-label--required">Label</label>
            `,
    }),
};

export const Small: Story = {
    render: () => ({
        template: `
            <label class="c-label c-label--small">Smaller Label</label>
            `,
    }),
};
