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
