/* eslint-disable import/no-extraneous-dependencies -- storybook is a dev dependency, so it's okay */

import type { Meta, StoryObj } from "@storybook/vue3";
import CcReport from "../../../library/Schoolbox/Curriculum/Reports/templates/vue/cc-report.vue";

/**
 * Storybook meta information for the `cc-report />` component
 */
const meta: Meta<typeof CcReport> = {
    component: CcReport,
    title: "Draft/Curriculum Coverage Report",
};
export default meta;
type Story = StoryObj<typeof CcReport>;

/**
 * Displays the default `<cc-report />`
 */
export const Default: Story = {
    render: (args: unknown) => ({
        components: { CcReport },
        setup(): { args: typeof args } {
            return { args };
        },
        template: `<cc-report />`,
    }),
};
