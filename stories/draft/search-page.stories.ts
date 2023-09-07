/**
 * Generate stories for the new search page
 */

import type { Meta, StoryObj } from "@storybook/vue3";
import { rest } from "msw";
import SearchPage from "../../library/Schoolbox/Search/templates/vue/search-page.vue";
import { data } from "./dataSearchPage";

let requestCount = 0; // number of times the endpoint has been called, note this won't be reset between stories

/**
 * Mock Service Worker handlers to load dummy data for testing
 */
const endpoint = "/search";
const subResultsEndpoint = "/getSubResults"; // endpoint to get sub results, is stores in dataSearchPage.ts
const mswHandlers = [
    rest.get(endpoint, (req, res, ctx) => {
        requestCount += 1;
        if (requestCount === 1) {
            return res(ctx.delay(2000), ctx.json(data));
        }
        return res(
            // on the second call we shorten the results so load more doesn't show anymore
            ctx.delay(2000),
            ctx.json({
                data: [
                    {
                        id: 4,
                        type: "user.parent",
                        title: "More results 1",
                    },
                    {
                        id: 4,
                        type: "user.parent",
                        title: "More results 2",
                    },
                ],
            })
        );
    }),
    rest.get(subResultsEndpoint, (req, res, ctx) => {
        return res(
            ctx.delay(2000),
            ctx.json({
                data: [
                    {
                        id: 4,
                        type: "user.parent",
                        title: "More sub results 1",
                    },
                    {
                        id: 4,
                        type: "user.parent",
                        title: "More sub results 2",
                    },
                    {
                        id: 4,
                        type: "user.parent",
                        title: "More sub results 3",
                    },
                    {
                        id: 4,
                        type: "user.parent",
                        title: "More sub results 4",
                    },
                ],
            })
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
        filterTypes: [
            {
                title: "Assessment",
                value: "assessment",
                subTypes: [
                    {
                        title: "Due Work",
                        value: "assessment.dueWork",
                    },
                    {
                        title: "Lesson Plan",
                        value: "assessment.lessonPlan",
                    },
                    {
                        title: "Project",
                        value: "assessment.project",
                    },
                ],
            },
            {
                title: "Badge",
                value: "event",
            },
            {
                title: "Event",
                value: "event",
            },
        ],
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
    title: "Draft/SearchPage",
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
                    return res(ctx.delay(2000), ctx.json({ data: [] }));
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
