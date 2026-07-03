import { mount } from "@vue/test-utils";
import IbReportFailedTable from "../ib-report-failed-table.vue";
import IbReportRegisteredTable from "../ib-report-registered-table.vue";
import IbReportStandardTable from "../ib-report-standard-table.vue";

jest.mock("../../store/ibReportUpdateLanguageStore", () => ({
    useIbReportUpdateLanguageStore: () => ({
        prompt: jest.fn(),
    }),
}));

const baseStudent = {
    id: 123,
    name: "Ada Lovelace",
    userId: 456,
    candidateDetailsStatus: "unconfirmed",
    ibCandidateNumber: null,
    missingFields: ["dateOfBirth", "nationality1"],
    subjects: {
        hl: [],
        sl: [],
        core: [],
    },
    updateLanguageEndpoint: null,
    updateConfirmationEndpoint: null,
    renotifyEndpoint: null,
};

const globalMountOptions = {
    stubs: {
        "ib-report-subject-labels": true,
        "sbx-icon": {
            props: ["name", "title"],
            template: '<span data-test="sbx-icon" :data-name="name" :title="title"></span>',
        },
        "sbx-pop-out": true,
    },
};

function findAlertTitle(wrapper) {
    return wrapper.find('sbx-icon[name="alert"], sbx-icon-stub[name="alert"], [data-name="alert"]').attributes("title");
}

test.each([
    [
        "standard table",
        () =>
            mount(IbReportStandardTable, {
                props: {
                    students: [{ ...baseStudent, stage: "confirmed" }],
                    searchLanguagesEndpoint: "/languages",
                    section: "confirmed",
                    activeSelectionSection: null,
                },
                global: globalMountOptions,
            }),
    ],
    [
        "failed table",
        () =>
            mount(IbReportFailedTable, {
                props: {
                    students: [{ ...baseStudent, stage: "failed", errorMessage: null }],
                    activeSelectionSection: null,
                },
                global: globalMountOptions,
            }),
    ],
    [
        "registered table",
        () =>
            mount(IbReportRegisteredTable, {
                props: {
                    students: [{ ...baseStudent, stage: "registered" }],
                },
                global: globalMountOptions,
            }),
    ],
])("shows missing candidate detail fields in the %s title", (tableName, mountTable) => {
    expect(findAlertTitle(mountTable())).toBe("Missing required candidate details: dateOfBirth, nationality1.");
});

test("falls back to the generic missing candidate details title when no fields are provided", () => {
    const wrapper = mount(IbReportRegisteredTable, {
        props: {
            students: [{ ...baseStudent, missingFields: [], stage: "registered" }],
        },
        global: globalMountOptions,
    });

    expect(findAlertTitle(wrapper)).toBe("Missing required candidate details.");
});
