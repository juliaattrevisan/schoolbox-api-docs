import type { Meta, StoryObj } from "@storybook/vue3";
import BottomDrawer from "./bottom-drawer.vue";

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof BottomDrawer> = {
    component: BottomDrawer,
    decorators: [
        (): { template: string } => ({
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
        setup(): { args: typeof args } {
            return { args };
        },
        // @ts-expect-error -- For now, args.default doesn't match Meta<typeof BottomDrawer>, but we want to show it as a slot
        // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
        template: `<bottom-drawer v-bind="args">${args.default}</bottom-drawer>`,
    }),
    argTypes: {
        // @ts-expect-error -- For now, args.default doesn't match Meta<typeof BottomDrawer>, but we want to show it as a slot
        default: {
            control: "text",
        },
    },
    args: {
        // @ts-expect-error -- For now, args.default doesn't match Meta<typeof BottomDrawer>, but we want to show it as a slot
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
