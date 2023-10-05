import type { Meta, StoryObj } from "@storybook/vue3";
import SbxFiltersDropdown from "@schoolboxVue/components/dropdown/sbx-filters-dropdown.ce.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof SbxFiltersDropdown> = {
    component: SbxFiltersDropdown,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Dropdowns/Filters Dropdown",
};
export default meta;
type Story = StoryObj<typeof SbxFiltersDropdown>;

/**
 * Shows the dropdown sitting in different regions of the viewport, which will determine which direction the dropdown opens.
 */
export const Default: Story = {
    render: () => ({
        components: { SbxFiltersDropdown },
        template: `
            <div class="l-flex-col l-flex--justify-space" style="height: 200px;">
                <div class="l-flex-row l-flex--justify-space">
                    <div content-id="dropdown-1">
                        <sbx-filters-dropdown content-id="dropdown-1"></sbx-filters-dropdown>
                    </div>
                </div>
            </div>
            <ul data-dropdown-content="" class="f-dropdown" id="dropdown-1">
                <li class="separator">Manage</li>
                <li><a>Course Markbook</a></li>
                <li><a>Delete</a></li>
            </ul>
        `,
    }),
};
