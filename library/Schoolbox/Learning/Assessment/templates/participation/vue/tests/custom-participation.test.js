import { nextTick } from "vue";
import { mount } from "@vue/test-utils";
import CustomParticipation from "../custom-participation.vue";
import { allParticipants } from "./allParticipantsData";

beforeEach(() => {});

const defaultProps = {
    allParticipants,
    getStudentsPastoralCountsUrl: "",
    getLearningPlanNewUrl: "",
    getAssessmentParticipation: "",
    getAssessmentParticipationNewActivity: "",
    assessmentId: 0,
    customisations: [],
    includeInExistingCustomisation: "",
};

test("all five participants are visible", () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
        },
    });
    expect(wrapper.findAll('[data-test^="custom-participation-row-"]')).toHaveLength(5);
});

test("select all check box is not visible when only one participant", () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
            allParticipants: [allParticipants[0]],
        },
    });
    expect(wrapper.find('[data-test^="custom-participation-select-all"]').exists()).toBe(false);
});

test("select all check box is visible when more than one participant", () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
        },
    });
    expect(wrapper.find('[data-test^="custom-participation-select-all"]').isVisible()).toBe(true);
});

test("bottom drawer is visible when two participants are selected", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
        },
    });
    const checkboxes = wrapper.findAll('[data-test^="custom-participation-checkbox-"]');
    await checkboxes[0].setValue(true);
    await checkboxes[1].setValue(true);
    expect(wrapper.find('[data-test="custom-participation-bottom-drawer"]').isVisible()).toBe(true);
});

test("When one of the three students is excluded, Copy Activity button does not appear", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
        },
    });
    const checkboxes = wrapper.findAll('[data-test^="custom-participation-checkbox-"]');
    await checkboxes[0].setValue(true);
    await checkboxes[1].setValue(true);
    await checkboxes[2].setValue(true);
    await wrapper.vm.$nextTick();
    const copyButton = wrapper.find('[data-test="custom-participation-copy-activity"]');
    expect(copyButton.exists()).toBe(false);
});

test("When two students are selected, and one is excluded, the Remove Customisation button appears in the bottom drawer", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
        },
    });
    const checkboxes = wrapper.findAll('[data-test^="custom-participation-checkbox-"]');
    await checkboxes[1].setValue(true);
    await checkboxes[2].setValue(true);
    expect(wrapper.find('[data-test="custom-participation-remove-customisation"]').exists()).toBe(true);
});

test("If a student is excluded and doesn't have a customised activity, Excluded is visible in their row", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
        },
    });
    const row = wrapper.find(`[data-test="custom-participation-row-${allParticipants[0].user.id}"]`);
    expect(row.text()).toContain("Excluded");
});

test("If a student is excluded and is part of a customised activity, Customised activity name is visible in their row", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
        },
    });
    const row = wrapper.find(`[data-test="custom-participation-row-${allParticipants[3].user.id}"]`);
    expect(row.text()).toContain("Final Assessment (Joffery)");
});

test("If a participant's user id is not set, their name is not wrapped in an <a> tag", async () => {
    const modifiedParticipants = allParticipants.map((p) => {
        if (p.user.id === 20) {
            return { ...p, user: { ...p.user, id: null } };
        }
        return p;
    });
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
            allParticipants: modifiedParticipants,
        },
    });
    const row = wrapper.find(`[data-test="custom-participation-row-${modifiedParticipants[0].user.id || "null"}"]`);
    expect(row.exists()).toBe(true);
    expect(row.find("a").exists()).toBe(false);
});

test("A participants preferred name is set and visible in their name", async () => {
    const modifiedParticipants = allParticipants.map((p) => {
        if (p.user.id === 20) {
            return { ...p, user: { ...p.user, preferredName: "Moshe P." } };
        }
        return p;
    });
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
            allParticipants: modifiedParticipants,
        },
    });
    const row = wrapper.find(`[data-test="custom-participation-row-${modifiedParticipants[0].user.id}"]`);
    expect(row.text()).toContain("Moshe P.");
});

test("When a load error occurs the load error note is visible ", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            ...defaultProps,
            allParticipants: [],
        },
    });
    wrapper.vm.loadError = "Some error occurred";
    await nextTick();
    expect(wrapper.find('[data-test="custom-participation-load-error"]').isVisible()).toBe(true);
    expect(wrapper.text()).toContain("Some error occurred");
});
