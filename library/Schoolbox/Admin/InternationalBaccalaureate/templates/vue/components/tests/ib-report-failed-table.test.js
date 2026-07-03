import { mount } from "@vue/test-utils";
import IbReportFailedTable from "../ib-report-failed-table.vue";

jest.mock("../../store/ibReportUpdateLanguageStore", () => ({
    useIbReportUpdateLanguageStore: () => ({
        prompt: jest.fn(),
    }),
}));

const failedStudent = {
    id: 123,
    name: "Ada Lovelace",
    userId: 456,
    candidateDetailsStatus: "confirmed",
    ibCandidateNumber: "000123",
    missingFields: [],
    subjects: {
        hl: [],
        sl: [],
        core: [],
    },
    updateLanguageEndpoint: null,
    updateConfirmationEndpoint: null,
    renotifyEndpoint: null,
    stage: "failed",
    errorMessage: "IBIS rejected the registration.",
};

function mountTable() {
    return mount(IbReportFailedTable, {
        props: {
            students: [failedStudent],
            activeSelectionSection: null,
        },
        global: {
            stubs: {
                "ib-report-subject-labels": true,
                "sbx-icon": true,
                "sbx-pop-out": true,
            },
        },
    });
}

test("emits registerViaApiSingle when registering a failed registration via API", async () => {
    const wrapper = mountTable();
    const registerButton = wrapper.findAll("button").find((button) => button.text().includes("Register via API"));

    expect(registerButton).toBeDefined();

    await registerButton.trigger("click");

    expect(wrapper.emitted("registerViaApiSingle")).toEqual([[failedStudent.id]]);
});
