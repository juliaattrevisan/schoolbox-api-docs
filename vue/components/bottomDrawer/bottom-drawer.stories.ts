import type { Meta, StoryObj } from "@storybook/vue3";
import BottomDrawer from "./bottom-drawer.vue";

/**
 * **Source**: `vue/components/bottomDrawer/bottom-drawer.vue`
 *
 * To display content that appears when requested from the bottom of the window.
 * Usually contains actions in response to selections on the page, for example choosing some users to edit.
 * Once +1 users is selected the drawer will slide in from the bottom of the window.
 *
 * **Note**: Due to this component using `getBoundingClientRect()` for positioning, it's best to view this component within the individual stories.
 * Viewing it within the preview windows will not show the correct positioning.
 */
const meta: Meta<typeof BottomDrawer> = {
    component: BottomDrawer,
    decorators: [
        () => ({
            template: `
            <div style="width: 100%; height: 500px">
                <div style="width: 80%; margin: 0 auto;">
                    <story />
                </div>
            </div>
            `,
        }),
    ],
    render: (args) => ({
        components: {
            BottomDrawer,
        },
        setup() {
            return { args };
        },
        template: `
            <bottom-drawer v-bind="args">
                ${args.default}
            </bottom-drawer>
        `,
    }),
    argTypes: {
        default: {
            control: "text",
        },
    },
    args: {
        default: "Any content can go here.",
        showDrawer: true,
        isAlwaysAnchored: true,
        unanchorWidth: 2000,
    },
    title: "Components/Drawers/Bottom Drawer",
};
export default meta;
type Story = StoryObj<typeof BottomDrawer>;

/**
 * Displays the default `<bottom-drawer/>`
 */
export const Default: Story = {};

/**
 * Shows the drawer unanchored from the bottom of the page
 */
export const Unanchored: Story = {
    args: {
        showDrawer: true,
        isAlwaysAnchored: false,
        unanchorWidth: 2000,
    },
};
