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
            <div id="container" class="l-flex-col l-flex--justify-space" style="height: 200px;">
                <ul class="filter-dropdown flex-list flex-wrap">
                <li class="checklist item filter-component">
                    <sbx-filters-dropdown content-id="dropdown-1" button-text="Category"></sbx-filters-dropdown>
                    <div id="dropdown-1" data-dropdown-content class="f-dropdown content">
                        <ul class="option-list">
                            <li>
                                <input
                                    id="filter_selection_22_1"
                                    type="checkbox"
                                    name="filter_selection[22][1]"
                                    value="1"
                                />
                                <label for="filter_selection_22_1" class="noselect">Learning</label>
                            </li>
                            <li>
                                <input
                                    id="filter_selection_22_2"
                                    type="checkbox"
                                    name="filter_selection[22][2]"
                                    value="1"
                                />
                                <label for="filter_selection_22_2" class="noselect"
                                    >Personal Development</label
                                >
                            </li>
                            <li>
                                <input
                                    id="filter_selection_22_3"
                                    type="checkbox"
                                    name="filter_selection[22][3]"
                                    value="1"
                                />
                                <label for="filter_selection_22_3" class="noselect"
                                    >Community</label
                                >
                            </li>
                            <li>
                                <input
                                    id="filter_selection_22_4"
                                    type="checkbox"
                                    name="filter_selection[22][4]"
                                    value="1"
                                />
                                <label for="filter_selection_22_4" class="noselect"
                                    >Extra-Curricular</label
                                >
                            </li>

                            <li>
                                <input
                                    id="filter_selection_22_5"
                                    type="checkbox"
                                    name="filter_selection[22][5]"
                                    value="1"
                                />
                                <label for="filter_selection_22_5" class="noselect"
                                    >Achievement Standards</label
                                >
                            </li>
                        </ul>
                    </div>
                    </li>
                </ul>
            </div>
        `,
    }),
};
