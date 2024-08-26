import type { Meta, StoryObj } from "@storybook/vue3";
import StorybookSlot from "../../../vue/components/storybook-slot.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof StorybookSlot> = {
    component: StorybookSlot,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Pop Out",
};
export default meta;
type Story = StoryObj<typeof StorybookSlot>;

/**
 * Shows the dropdown sitting in different regions of the viewport, which will determine which direction the dropdown opens.
 */
export const Default: Story = {
    render: () => ({
        components: { StorybookSlot },
        template: `
<div id="container" class="l-flex-col l-flex--justify-space" style="height: 300px; min-height: 300px;">
    <div class="l-flex-row l-flex--justify-space">
        <sbx-pop-out content-id="dropdown-1" is-close-on-content-click></sbx-pop-out>
        <sbx-pop-out content-id="dropdown-2">
            <div slot="icon">
                <sbx-icon name="link"></sbx-icon>
            </div>
        </sbx-pop-out>
        <sbx-pop-out content-id="dropdown-5" button-text="Filter" is-arrow-icon></sbx-pop-out>
    </div>
    <div class="l-flex-row l-flex--justify-space">
        <sbx-pop-out content-id="dropdown-3" button-text="Modify Participation"></sbx-pop-out>
        <sbx-pop-out content-id="dropdown-4"></sbx-pop-out>
    </div>
</div>
<ul data-dropdown-content="" class="f-dropdown" id="dropdown-1">
    <li class="separator">Manage</li>
    <li><a>Course Markbook</a></li>
    <li><a>Delete</a></li>
</ul>
<ul data-dropdown-content="" class="f-dropdown" id="dropdown-2">
    <li class="separator">Manage</li>
    <li><a>Course Markbook</a></li>
    <li><a>Delete</a></li>
</ul>
<ul data-dropdown-content="" class="f-dropdown" id="dropdown-3">
    <li class="separator">Manage</li>
    <li><a>Course Markbook</a></li>
    <li><a>Delete</a></li>
</ul>
<ul data-dropdown-content="" class="f-dropdown" id="dropdown-4">
    <li class="separator">Manage</li>
    <li><a>Course Markbook</a></li>
    <li><a>Delete</a></li>
</ul>
<ul data-dropdown-content="" class="f-dropdown" id="dropdown-5">
    <li><a>Learning</a></li>
    <li><a>Personal Development</a></li>
    <li><a>Community</a></li>
    <li><a>Extra-Curriculuar</a></li>
</ul>
        `,
    }),
};
