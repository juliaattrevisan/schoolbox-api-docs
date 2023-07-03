import type { Meta, StoryObj } from "@storybook/vue3";
import { rest } from "msw";
import SearchPage from "../../library/Schoolbox/Search/templates/vue/search-page.vue";
import { data } from "./dataSearchPage";

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

const meta: Meta<typeof SearchPage> = {
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

export const Primary: Story = {
    render: (args: unknown) => ({
        components: { SearchPage },
        setup() {
            return { args };
        },
        template: `<search-page v-bind="args" />`,
    }),
    args: {
        endpoint,
    },
};
