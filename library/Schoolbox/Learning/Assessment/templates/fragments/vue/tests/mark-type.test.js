import { mount } from "@vue/test-utils";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import MarkType from "../mark-type.vue";

jest.mock("@js/terminology/rosettaStone", () => ({
    grades: {
        proper: jest.fn(() => "Grades"),
    },
    grading: {
        proper: jest.fn(() => "Grading"),
    },
}));

const defaultProps = {
    context: "rubric-0-capability",
    allMarkTypes: [
        { name: "No Mark", slug: MarkTypeSlug.NONE },
        { name: "Letter Grade", slug: MarkTypeSlug.LETTER },
    ],
    allGradeSchemes: [
        {
            id: 7,
            name: "Default",
            gradeLevels: [{ lowerBound: 0, caption: "N" }],
        },
    ],
    defaultGradeGroup: 7,
    renderMaxMark: false,
    courseManagedReadonly: false,
    selectedMarkType: MarkTypeSlug.LETTER,
    selectedGradeGroup: null,
};

test("sets the default grade group when letter grade is selected without a grade group", () => {
    const wrapper = mount(MarkType, {
        props: defaultProps,
        global: {
            stubs: {
                BasicModal: true,
            },
        },
    });

    expect(wrapper.emitted("update:selectedGradeGroup")).toEqual([[7]]);
});
