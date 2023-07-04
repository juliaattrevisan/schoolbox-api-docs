/**
 * Generate stories for the new search page
 */

import type { Meta, StoryObj } from "@storybook/vue3";
import { rest } from "msw";
import SearchPage from "../../library/Schoolbox/Search/templates/vue/search-page.vue";
import { data } from "./dataSearchPage";

/**
 * Mock Service Worker handlers to load dummy data for testing
 */
const endpoint = "/search";
const mswHandlers = [
    rest.get(endpoint, (req, res, ctx) => {
        return res(
            // we grab an endpoint and then modify the data before it's returned
            ctx.delay(3000),
            ctx.json(data)
        );
    }),
];

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof SearchPage> = {
    args: {
        endpoint,
    },
    component: SearchPage,
    decorators: [
        () => ({
            template: `
                <div id="container">
                    <div id="content">
                        <story />
                    </div>
                </div>
            `,
        }),
    ],
    parameters: {
        msw: {
            handlers: mswHandlers,
        },
    },
    title: "Temp/SearchPage",
};
export default meta;
type Story = StoryObj<typeof SearchPage>;

/**
 * The default story, to show the search page
 */
export const Default: Story = {
    render: (args: unknown) => ({
        components: { SearchPage },
        setup() {
            return { args };
        },
        template: `<search-page v-bind="args" />`,
    }),
};

/**
 * To show the search page with no results,
 * we update the msw handlers to return an empty array
 */
export const NoResults: Story = {
    ...Default,
    parameters: {
        msw: {
            handlers: [
                rest.get(endpoint, (req, res, ctx) => {
                    return res(ctx.delay(2000), ctx.json([]));
                }),
                ...mswHandlers,
            ],
        },
    },
};

/**
 * To show an error if the search fails,
 * we update the msw handlers to return a 500 error
 */
export const Error: Story = {
    ...Default,
    parameters: {
        msw: {
            handlers: [
                rest.get(endpoint, (req, res, ctx) => {
                    return res(ctx.delay(1000), ctx.status(500));
                }),
                ...mswHandlers,
            ],
        },
    },
};
