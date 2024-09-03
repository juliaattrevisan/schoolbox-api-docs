import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * **Styles**: `www/static/css/core/ui/_chip.scss`
 *
 * ## What are they?
 *
 * They are a type of selector that lends itself to sets of options with a common theme. Good examples of where these could be used are...
 *
 * -   A list of assessment types within the settings of a component in Schoolbox
 * -   Selectors for the number of bedrooms, bathrooms or parking spots in a hotel / homeshare / real estate website or app
 * -   They are _not_ a complete replacement for checkboxes, nor do they function as a radio selectors in our current implementation (though the Material Design docs do say this functionality is within spec so we may add radio selector chips later)
 *
 * ## When should I consider using chips?
 *
 * If you meet any or all of the below
 *
 * -   A good use case is when selectable items are related by context (for example, filter options that are visually grouped together in our UI and contextually relevant to each other)
 * -   When you want to preserve vertical space and items aren't expected to sit in an explicit grid
 * -   When you're okay with or specifically want the behaviour for items to automatically wrap to lower rows when horizontal screen size is reduced
 * -   When you have more than a single thing that needs to be selected or added to a list. Chips should _always_ appear in a set and _never_ appear in a location that allows only a single chip. These are not a direct replacement for checkboxes, nor are they buttons
 * -   When you have a set of options, you don't want them to wrap, and you want a component that allows users to scroll horizontally if there's overflow
 */
const meta: Meta<typeof StorybookSlot> = {
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
    title: "Components/Form/Chips",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Using `.c-chips`
 */
export const Default: Story = {
    render: () => ({
        template: `
<ul class="c-chips">
    <li><input type="checkbox" id="cb-1-1" /><label for="cb-1-1">Apple<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-1-2" /><label for="cb-1-2">Banana<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-1-3" /><label for="cb-1-3">Carrot<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-1-4" /><label for="cb-1-4">Durian<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-1-5" /><label for="cb-1-5">Endive<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-1-6" /><label for="cb-1-6">Fig<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-1-7" /><label for="cb-1-7">Grape<span class="c-chip__icon icon-tick"></span></label></li>
</ul>
            `,
    }),
};

/**
 * Chips are under no obligation to use the tick as their selected icon - all Schoolbox icons are supported `.icon-sunny`
 */
export const Icons: Story = {
    render: () => ({
        template: `
<ul class="c-chips">
    <li><input type="checkbox" id="cb-12-1" /><label for="cb-12-1">Sunny / Clear<span class="c-chip__icon icon-sunny"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-12-2" /><label for="cb-12-2">Mostly Cloudy<span class="c-chip__icon icon-mostly-cloudy"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-12-3" /><label for="cb-12-3">Cloudy<span class="c-chip__icon icon-cloudy"></span></label></li>
    <li><input type="checkbox" id="cb-12-4" /><label for="cb-12-4">Rain / Chance of Rain<span class="c-chip__icon icon-rain"></span></label></li>
    <li><input type="checkbox" id="cb-12-5" /><label for="cb-12-5">Storm / Stormy Change<span class="c-chip__icon icon-storm"></span></label></li>
</ul>
            `,
    }),
};

/**
 * For right-to-left languages, add `dir="rtl"` to the parent element
 */
export const RTL: Story = {
    render: () => ({
        template: `
<ul class="c-chips" dir="rtl">
    <li><input type="checkbox" id="cb-10-1" /><label for="cb-10-1">Apple<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-10-2" /><label for="cb-10-2">Banana<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-10-3" /><label for="cb-10-3">Carrot<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-10-4" /><label for="cb-10-4">Durian<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-10-5" /><label for="cb-10-5">Endive<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-10-6" /><label for="cb-10-6">Fig<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-10-7" /><label for="cb-10-7">Grape<span class="c-chip__icon icon-tick"></span></label></li>
</ul>
            `,
    }),
};

/**
 * Are you using a scrollable container? Add `.c-chips--scroll` to the parent element
 */
export const Scrollable: Story = {
    render: () => ({
        template: `
<ul class="c-chips c-chips--scroll">
    <li><input type="checkbox" id="cb-11-1" /><label for="cb-11-1">Apple<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-11-2" /><label for="cb-11-2">Banana<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" checked="checked" id="cb-11-3" /><label for="cb-11-3">Carrot<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-4" /><label for="cb-11-4">Durian<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-5" /><label for="cb-11-5">Endive<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-6" /><label for="cb-11-6">Fig<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-7" /><label for="cb-11-7">Grape<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-8" /><label for="cb-11-8">Hazelnut<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-9" /><label for="cb-11-9">Iceberg Lettuce<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-10" /><label for="cb-11-10">Jackfruit<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-11" /><label for="cb-11-11">Kiwi<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-12" /><label for="cb-11-12">Lemon<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-13" /><label for="cb-11-13">Mandarine<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-14" /><label for="cb-11-14">Nectarine<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-15" /><label for="cb-11-15">Orange<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-16" /><label for="cb-11-16">Peach<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-17" /><label for="cb-11-17">Quince<span class="c-chip__icon icon-tick"></span></label></li>
    <li><input type="checkbox" id="cb-11-18" /><label for="cb-11-18">Rocket<span class="c-chip__icon icon-tick"></span></label></li>
</ul>
            `,
    }),
};
