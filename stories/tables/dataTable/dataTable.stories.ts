import type { Meta, StoryObj } from "@storybook/vue3";
import { rest } from "msw";
import DataTable from "@schoolboxVue/components/dataTable/data-table.vue";
import { data } from "./dataTableMockData";

/**
 * Mock Service Worker handlers to load dummy data for testing
 */
const endpoint = "/mock-data";
const mswHandlers = [
    rest.get(endpoint, (req, res, ctx) => {
        return res(
            // we grab an endpoint and then modify the data before it's returned
            ctx.delay(3000),
            ctx.json(data)
        );
    }),
];

const columnsConfig = [
    {
        key: "portrait_url",
        heading: "Image",
        class: "portrait-td",
        isSortable: false,
        template: {
            component: "image",
            componentData: {
                src: "portrait_url",
                href: "profile_url",
                title: "View profile",
                alt: "Portrait photo of user",
            },
        },
    },
    {
        key: "StaffPreferredName",
        heading: "First name",
        class: "",
        template: {
            component: "link",
            componentData: {
                text: "StaffPreferredName",
                href: "profile_url",
                title: "View profile",
            },
        },
    },
    {
        key: "StaffSurname",
        heading: "Surname",
        class: "",
        template: {
            component: "link",
            componentData: {
                text: "StaffSurname",
                href: "profile_url",
                title: "View profile",
            },
        },
    },
];

const tableOptions = {
    orderBy: {
        ascending: true,
        column: "StaffSurname",
    },
    texts: {
        count: "{from}-{to} of {count} members|{count} members|1 member",
        filterPlaceholder: "Search staff members...",
        noResults: "No matching staff members",
    },
};

/**
 * Storybook meta data, any default properties that are passed into every story
 * https://storybook.js.org/docs/vue/writing-stories/introduction
 */
const meta: Meta<typeof DataTable> = {
    args: {
        title: "Whoop",
        endpoint,
        columnsConfig,
        tableOptions,
    },
    component: DataTable,
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
    title: "Tables/DataTable",
};
export default meta;
type Story = StoryObj<typeof DataTable>;

/**
 * The default story, to show the data table
 */
export const Default: Story = {
    render: (args: unknown) => ({
        components: { DataTable },
        setup() {
            return { args };
        },
        template: `<data-table v-bind="args" />`,
    }),
};
