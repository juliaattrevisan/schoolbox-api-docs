import { mount } from "@vue/test-utils";
import RubricBuilder from "../rubric-builder.vue";

function makeRubric(id, maxValue, weight = 1) {
    return {
        id,
        title: `Rubric ${id}`,
        weight,
        useMarkRange: false,
        levels: [],
        capabilities: [
            {
                id,
                name: `Capability ${id}`,
                inUseExcludingAssessment: false,
                maxValue,
                indicators: [],
            },
        ],
        capabilityMarkType: null,
        capabilityGradeGroup: null,
    };
}

function mountRubricBuilder(rubrics) {
    document.body.innerHTML = `
        <input id="maxMark" value="5">
        <a href="#" data-assign-max-marks-from-rubric="maxMark">
            Assign <span data-rubric-sum></span> from <span data-rubric-term>Rubric</span>
        </a>
    `;

    return mount(RubricBuilder, {
        attachTo: document.body,
        props: {
            rubricGridsPayload: rubrics,
            rubricBlankGrid: makeRubric(0, 0),
            resultSets: [],
            capabilityMarkTypes: [],
            capabilityGradingSchemes: [],
            defaultGradeGroup: 1,
            maxLevels: 5,
            folderId: 1,
            assessmentId: null,
            routeRubricCreate: "/rubric/create",
            routeRubricImport: "/rubric/import/{storageTuple}",
            routeRubricDownload: null,
        },
        global: {
            stubs: {
                FieldsetCollapsible: { template: "<fieldset><slot name=\"legend\" /><slot /></fieldset>" },
                BasicModal: true,
                RbRubricFields: true,
                RbEditableGrid: true,
                RbRubricDownload: true,
            },
        },
    });
}

describe("rubric-builder", () => {
    afterEach(() => {
        document.body.innerHTML = "";
    });

    it("dispatches input when assigning the rubric max mark", async () => {
        const wrapper = mountRubricBuilder([makeRubric(1, 7)]);
        const input = document.getElementById("maxMark");
        const assignLink = document.querySelector("[data-assign-max-marks-from-rubric=\"maxMark\"]");
        const inputListener = jest.fn();

        input.addEventListener("input", inputListener);
        assignLink.click();

        expect(input.value).toBe("7");
        expect(inputListener).toHaveBeenCalledTimes(1);

        wrapper.unmount();
    });
});
