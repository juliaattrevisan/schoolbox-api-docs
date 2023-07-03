import SearchPage from "../../library/Schoolbox/Search/templates/vue/search-page.vue";
import { rest } from "msw";
import { data } from "./dataSearchPage";

const endpoint = "/search";

const mswHandlers = [
    rest.get(endpoint, (req, res, ctx) => {
        return res(
            //we grab an endpoint and then modify the data before it's returned
            ctx.delay(1000),
            ctx.json(data)
        );
    }),
];

export default {
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

const Template = (args, { argTypes }) => ({
    components: { SearchPage },
    setup() {
        return { args };
    },
    template: `
        <search-page v-bind="args" />
        `,
});

export const Default = Template.bind({});
