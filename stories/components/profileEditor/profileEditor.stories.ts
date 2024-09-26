/* eslint-disable import/no-extraneous-dependencies -- storybook is a dev dependency, so it's okay */

import type { Meta, StoryObj } from "@storybook/vue3";
// import { http, HttpResponse } from "msw";
import ProfileEditor from "@schoolboxLibrary/Admin/Profile/Editor/vue/profile-editor.vue";
import type { ProfileEditorEndpoints } from "@schoolboxLibrary/Admin/Profile/Editor/vue/profileEditorTypes";
import { mockData } from "./peMockData";

/**
 * Mock Service Worker handlers to load dummy data for testing
 */
const endpoints: ProfileEditorEndpoints = {
    adminProfileEditorPostFieldAdd: "nothing here for now...",
    adminProfileEditorPostFieldRemove: "nothing here for now...",
    adminProfileEditorPostFieldSettings: "nothing here for now...",
    adminProfileEditorPostFieldSequence: "nothing here for now...",
};

// const mswHandlers = [
//     http.get(endpoints.profileEditorGet, () => {
//         return HttpResponse.json(mockData);
//     }),
// ];

/**
 * Storybook meta information for the `<profile-editor />` component
 */
const meta: Meta<typeof ProfileEditor> = {
    args: {
        endpoints,
        payload: mockData,
    },
    component: ProfileEditor,
    // parameters: {
    //     msw: {
    //         handlers: mswHandlers,
    //     },
    // },
    title: "Draft/Profile Editor",
};
export default meta;
type Story = StoryObj<typeof ProfileEditor>;

/**
 * Displays the default `<profile-editor />`
 */
export const Default: Story = {
    render: (args: unknown) => ({
        components: { ProfileEditor },
        setup(): { args: typeof args } {
            return { args };
        },
        template: `<profile-editor v-bind="args" />`,
    }),
};
