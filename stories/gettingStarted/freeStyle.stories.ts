import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../vue/components/storybook-slot.vue";

/**
 * **Source**: `vue/components/generic/sbx-free-style.ce.vue`
 *
 * This space can be used to style new components quickly without our existing styles interfering. Use the linked Vue component to add the relevant html markup within the `<template>` and the new styles within the `<style>`.
 *
 * Once the new component is appropriately set up:
 *
 * 1. The `<style>` can be copied into a new `.scss` file and replaced with an `@import` to the relevant file
 * 2. The new `.scss` file can be added where appropriate within `www/static/css/core/core.scss`
 * 3. The markup within `<template>` can also be added to the `InsideSchoolboxStyles` story within `stories/gettingStarted/freeStyle.stories.ts`
 *
 * This way we can see both how the new component looks **outside** and **inside** our current style setup. This will help us to add any relevant overrides to ensure the new styles are still completely respected within our current style setup.
 * When adding any overrides, please ensure they are marked with a comment to explain why they are needed and are prefixed with the `^foundation-fixes^` comment. This will help us to keep track of any overrides and ensure they are removed when they are no longer needed.
 *
 * Once both **outside** and **inside** are consistent, the component can be brought into its own space within Storybook to finalise and document.
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        (): { template: string } => ({
            template: `
                <div class="c-island" style="height: 500px;">
                    <story />
                </div>
            `,
        }),
    ],
    parameters: {
        docs: {
            controls: {
                exclude: ["default"],
            },
        },
    },
    title: "Getting Started/Contributing/Free Style",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Styles within `<sbx-free-style>` won't be affected by the Schoolbox CSS
 */
export const OutsideSchoolboxStyles: Story = {
    render: () => ({
        template: `
            <sbx-free-style></sbx-free-style>
        `,
    }),
};

/**
 * Once the you've styled the component, the markup can be added to this story to have the existing Schoolbox styles applied
 */
export const InsideSchoolboxStyles: Story = {
    render: () => ({
        template: `
<div class="c-select">
    <select id="standard-select">
        <option value="Option 1">Option 1</option>
        <option value="Option 2">Option 2</option>
        <option value="Option 3">Option 3</option>
        <option value="Option 4">Option 4</option>
        <option value="Option 5">Option 5</option>
        <option value="Option length">Option that has too long of a value to fit</option>
    </select>
</div>
        `,
    }),
};
