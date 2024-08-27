/* eslint-disable import/no-extraneous-dependencies -- storybook is a dev dependency, so it's okay */

import type { Meta, StoryObj } from "@storybook/vue3";
import { http, HttpResponse } from "msw";
import DataTable from "@schoolboxVue/components/dataTable/data-table.vue";
import { ColumnConfig } from "@schoolboxVue/components/dataTable/types/dataTableTypes";
import { TableOptions } from "@schoolboxVue/components/vueTables2/types/vueTables2Types";
import { data } from "./dataTableMockData";

/**
 * Mock Service Worker handlers to load dummy data for testing
 */
const endpoint = "/mock-data";
const mswHandlers = [
    http.get(endpoint, () => {
        return HttpResponse.json(data);
    }),
];

const columnsConfig: ColumnConfig[] = [
    {
        key: "portrait_url",
        heading: "Image",
        class: "portrait-td",
        isSortable: false,
        template: {
            key: "portrait_url",
            component: "image",
            componentData: {
                src: "portrait_url",
                href: "profile_url",
                title: "View profile",
                alt: "Portrait photo of user",
                imgClass: "img--round",
            },
        },
    },
    {
        key: "StaffFirstname",
        heading: "First name",
        template: {
            key: "StaffFirstname",
            component: "link",
            componentData: {
                text: "StaffFirstname",
                href: "profile_url",
                title: "View profile",
                hasNoWrap: true,
            },
        },
    },
    {
        key: "StaffSurname",
        heading: "Surname",
        template: {
            key: "StaffSurname",
            component: "link",
            componentData: {
                text: "StaffSurname",
                href: "profile_url",
                title: "View profile",
                hasNoWrap: true,
            },
        },
    },
    {
        key: "fullname",
        heading: "Full name",
        isHidden: true, // this column is hidden by default
    },
    {
        key: "StaffDepartment",
        heading: "Department",
        class: "hide-for-medium-down",
    },
    {
        key: "StaffOccupEmail",
        heading: "Email",
        template: {
            key: "StaffOccupEmail",
            component: "link",
            componentData: {
                text: "StaffOccupEmail",
                href: "StaffOccupEmail",
                prependHref: "mailto:",
                title: "Email user",
                hasNoWrap: true,
            },
        },
    },
];

const tableOptions: TableOptions = {
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
        title: "Staff Directory",
        endpoint,
        columnsConfig,
        tableOptions,
        id: "staff-directory",
    },
    component: DataTable,
    decorators: [
        () => ({
            template: `
                <story />
            `,
        }),
    ],
    parameters: {
        msw: {
            handlers: mswHandlers,
        },
    },
    title: "Components/Tables/DataTable",
};
export default meta;
type Story = StoryObj<typeof DataTable>;

/**
 * Displays the default `<data-table/>`
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

/**
 * What's displayed when there's an error loading the data
 */
export const Error: Story = {
    ...Default,
    parameters: {
        msw: {
            handlers: [
                http.get(endpoint, () => {
                    return new HttpResponse(null, { status: 401 });
                }),
                ...mswHandlers,
            ],
        },
    },
};
