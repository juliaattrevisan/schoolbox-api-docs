import { nextTick } from "vue";
import { mount } from "@vue/test-utils";
import CustomParticipation from "../custom-participation.vue";
import { allParticipants } from "./allParticipantsData";

beforeEach(() => {});

test("all four participants are visible", () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            allParticipants,
            getStudentsPastoralCountsUrl: "",
        },
    });
    expect(wrapper.findAll('[data-test^="custom-participation-row-"]')).toHaveLength(4);
});

test("select all check box is not visible when only one participant", () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            allParticipants: [allParticipants[0]],
            getStudentsPastoralCountsUrl: "",
        },
    });
    expect(wrapper.find('[data-test^="custom-participation-select-all"]').exists()).toBe(false);
});

test("select all check box is visible when more than one participant", () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            allParticipants,
            getStudentsPastoralCountsUrl: "",
        },
    });
    expect(wrapper.find('[data-test^="custom-participation-select-all"]').isVisible()).toBe(true);
});

test("bottom drawer is visible when two participants are selected", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            allParticipants,
            getStudentsPastoralCountsUrl: "",
        },
    });

    const checkboxes = wrapper.findAll('[data-test^="custom-participation-checkbox-"]');
    await checkboxes[0].setValue(true);
    await checkboxes[1].setValue(true);

    expect(wrapper.find('[data-test="custom-participation-bottom-drawer"]').isVisible()).toBe(true);
});

test("When one of the three students is excluded, Copy Activity button does not appear", async () => {
    const modified = allParticipants.slice(0, 3).map((p, index) => {
        if (index === 2) {
            return { ...p, excludedAt: "2025-06-20T10:00:00+10:00" };
        }
        return p;
    });

    const wrapper = mount(CustomParticipation, {
        props: {
            allParticipants: modified,
            getStudentsPastoralCountsUrl: "",
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
            allParticipants,
            getStudentsPastoralCountsUrl: "",
        },
    });

    const checkboxes = wrapper.findAll('[data-test^="custom-participation-checkbox-"]');
    await checkboxes[1].setValue(true);
    await checkboxes[2].setValue(true);

    expect(wrapper.find('[data-test="custom-participation-remove-customisation"]').exists()).toBe(true);
});

test("If a student is excluded, Excluded is visible in their row", async () => {
    const wrapper = mount(CustomParticipation, {
        props: {
            allParticipants,
            getStudentsPastoralCountsUrl: "",
        },
    });

    const row = wrapper.find(`[data-test="custom-participation-row-${allParticipants[0].user.id}"]`);
    expect(row.text()).toContain("Excluded");
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
            allParticipants: modifiedParticipants,
            getStudentsPastoralCountsUrl: "",
        },
    });

    const row = wrapper.find('[data-test="custom-participation-row-null"]');
    expect(row.find("a").exists()).toBe(false);
});
