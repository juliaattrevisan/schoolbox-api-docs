import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * ![draft](/draft.png)
 *
 * **Styles**: `www/static/css/core/ui/forms/_input-range.scss`
 *
 * [**mdn**](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/range)
 *
 * `<input>` elements of type range let the user specify a numeric value which must be no less than a given value, and no more than another given value.
 *
 * ## Properties
 *
 * | Property   | Type     | Description                                                                                 |
 * | ---------- | -------- | ------------------------------------------------------------------------------------------- |
 * | `min` \*   | `number` | The lowest value in the range of permitted values                                           |
 * | `max` \*   | `number` | The greatest value in the range of permitted values                                         |
 * | `value` \* | `number` | Current value of the range input                                                            |
 * | `step`     | `number` | The step attribute is a number that specifies the granularity that the value must adhere to |
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="c-island">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Form/Input Range",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

export const Default: Story = {
    render: () => ({
        template: `
<div class="c-form-element c-input-range">
    <label class="c-label" for="column-width">Column Width</label>
    <input class="c-input-range__input" type="range" id="column-width" name="column-width" min="3" max="9" step="1" value="7" />
</div>
        `,
    }),
};
