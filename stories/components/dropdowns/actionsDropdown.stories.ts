import type { Meta, StoryObj } from "@storybook/vue3";
import WcActionsDropdown from "@schoolboxVue/components/dropDown/wc-actions-dropdown.ce.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof WcActionsDropdown> = {
    component: WcActionsDropdown,
    decorators: [
        () => ({
            template: `
                <div class="l-pad-xl">
                    <story />
                </div>
            `,
        }),
    ],
    title: "Components/Dropdowns/Actions Dropdown",
};
export default meta;
type Story = StoryObj<typeof WcActionsDropdown>;

/**
 *
 */
export const Default: Story = {
    render: () => ({
        components: { WcActionsDropdown },
        template: `
            <div class="l-flex-col l-flex--justify-space" style="height: 600px;">
                <div class="l-flex-row l-flex--justify-space">
                    <div content-id="dropdown-1">
                        <wc-actions-dropdown content-id="dropdown-1"></wc-actions-dropdown>
                    </div>
                    <div content-id="dropdown-2">
                        <wc-actions-dropdown content-id="dropdown-2">
                            <template #icon>
                                <svg
                                    version="1.2"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 16 16"
                                    width="16"
                                    height="16"
                                    aria-hidden="true"
                                    focusable="false"
                                    class="c-icon"
                                >
                                    <path
                                        d="m3 10c-0.5 0-1-0.5-1-1v-2c0-0.5 0.5-1 1-1h1 2.8c-0.4-1.2-1.5-2-2.8-2h-1c-1.6 0-3 1.3-3 3v2c0 1.6 1.4 3 3 3h1c1.3 0 2.4-0.8 2.8-2h-2.8z"
                                    />
                                    <path
                                        d="m13 4h-1c-1.3 0-2.4 0.8-2.8 2h2.8 1c0.5 0 1 0.5 1 1v2c0 0.5-0.5 1-1 1h-1-2.8c0.4 1.2 1.5 2 2.8 2h1c1.7 0 3-1.4 3-3v-2c0-1.7-1.3-3-3-3z"
                                    />
                                    <path d="m12 8c0 0.6-0.4 1-1 1h-6c-0.6 0-1-0.4-1-1 0-0.6 0.4-1 1-1h6c0.6 0 1 0.4 1 1z" />
                                </svg>
                            </template>
                        </wc-actions-dropdown>
                    </div>
                </div>
                <div class="l-flex-row l-flex--justify-space">
                    <div content-id="dropdown-3">
                        <wc-actions-dropdown content-id="dropdown-3" button-text="Modify Participation"></wc-actions-dropdown>
                    </div>
                    <div content-id="dropdown-4">
                        <wc-actions-dropdown content-id="dropdown-4"></wc-actions-dropdown>
                    </div>
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
        `,
    }),
};
