import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    title: "Chips/Chips",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Using `.sb-chips`
 */
export const Default: Story = {
    render: (args: unknown) => ({
        template: `
            <ul class="sb-chips u-gap--xs u-padding-m">
                <li><input type="checkbox" id="cb-1-1" /><label for="cb-1-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-1-2" /><label for="cb-1-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-1-3" /><label for="cb-1-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-1-4" /><label for="cb-1-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-1-5" /><label for="cb-1-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-1-6" /><label for="cb-1-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-1-7" /><label for="cb-1-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            `,
    }),
};

/**
 * Rounded edges added with `.sb-chips--rounded`
 */
export const Rounded: Story = {
    render: (args: unknown) => ({
        template: `
            <ul class="sb-chips sb-chips--rounded u-gap--xs u-padding-m">
                <li><input type="checkbox" id="cb-7-1" /><label for="cb-7-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-7-2" /><label for="cb-7-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-7-3" /><label for="cb-7-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-7-4" /><label for="cb-7-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-7-5" /><label for="cb-7-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-7-6" /><label for="cb-7-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-7-7" /><label for="cb-7-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            `,
    }),
};

/**
 * Background removed with `.sb-chips--no-background`
 */
export const NoBackground: Story = {
    render: (args: unknown) => ({
        template: `
            <ul class="sb-chips sb-chips--rounded sb-chips--no-background u-gap--xs u-padding-m">
                <li><input type="checkbox" id="cb-8-1" /><label for="cb-8-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-8-2" /><label for="cb-8-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-8-3" /><label for="cb-8-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-8-4" /><label for="cb-8-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-8-5" /><label for="cb-8-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-8-6" /><label for="cb-8-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-8-7" /><label for="cb-8-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            `,
    }),
};

/**
 * Border removed with `.sb-chips--no-border`
 */
export const NoBorder: Story = {
    render: (args: unknown) => ({
        template: `
            <ul class="sb-chips sb-chips--rounded sb-chips--no-border u-gap--xs u-padding-m">
                <li><input type="checkbox" id="cb-9-1" /><label for="cb-9-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-9-2" /><label for="cb-9-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-9-3" /><label for="cb-9-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-9-4" /><label for="cb-9-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-9-5" /><label for="cb-9-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-9-6" /><label for="cb-9-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-9-7" /><label for="cb-9-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            `,
    }),
};

/**
 * Chips are under no obligation to use the tick as their selected icon - all Schoolbox icons are supported
 */
export const Icons: Story = {
    render: (args: unknown) => ({
        template: `
            <ul class="sb-chips sb-chips--rounded u-gap--xs u-padding-m">
                <li><input type="checkbox" id="cb-12-1" /><label for="cb-12-1">Sunny / Clear<span class="sb-chip__icon icon-sunny"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-12-2" /><label for="cb-12-2">Mostly Cloudy<span class="sb-chip__icon icon-mostly-cloudy"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-12-3" /><label for="cb-12-3">Cloudy<span class="sb-chip__icon icon-cloudy"></span></label></span></li>
                <li><input type="checkbox" id="cb-12-4" /><label for="cb-12-4">Rain / Chance of Rain<span class="sb-chip__icon icon-rain"></span></label></li>
                <li><input type="checkbox" id="cb-12-5" /><label for="cb-12-5">Storm / Stormy Change<span class="sb-chip__icon icon-storm"></span></label></li>
            </ul>
            `,
    }),
};

/**
 * For right-to-left languages, add `dir="rtl"` to the parent element
 */
export const RTL: Story = {
    render: (args: unknown) => ({
        template: `
            <ul class="sb-chips u-gap--xs u-padding-m" dir="rtl">
                <li><input type="checkbox" id="cb-10-1" /><label for="cb-10-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-10-2" /><label for="cb-10-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-10-3" /><label for="cb-10-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-10-4" /><label for="cb-10-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-10-5" /><label for="cb-10-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-10-6" /><label for="cb-10-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-10-7" /><label for="cb-10-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            `,
    }),
};

/**
 * Are you using a scrollable container? Add `.sb-chips--scroll` to the parent element
 */
export const Scrollable: Story = {
    render: (args: unknown) => ({
        template: `
            <ul class="sb-chips sb-chips--scroll u-gap--xs u-padding-m">
                <li><input type="checkbox" id="cb-11-1" /><label for="cb-11-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-11-2" /><label for="cb-11-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" checked="checked" id="cb-11-3" /><label for="cb-11-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-11-4" /><label for="cb-11-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-5" /><label for="cb-11-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-6" /><label for="cb-11-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-7" /><label for="cb-11-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-8" /><label for="cb-11-8">Hazelnut<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-9" /><label for="cb-11-9">Iceberg Lettuce<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-10" /><label for="cb-11-10">Jackfruit<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-11" /><label for="cb-11-11">Kiwi<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-12" /><label for="cb-11-12">Lemon<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-13" /><label for="cb-11-13">Mandarine<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-14" /><label for="cb-11-14">Nectarine<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-15" /><label for="cb-11-15">Orange<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-16" /><label for="cb-11-16">Peach<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-17" /><label for="cb-11-17">Quince<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-11-18" /><label for="cb-11-18">Rocket<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            `,
    }),
};
