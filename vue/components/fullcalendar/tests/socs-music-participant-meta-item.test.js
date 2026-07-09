import { mount } from "@vue/test-utils";
import SocsMusicParticipantMetaItem from "../socs-music-participant-meta-item.vue";

const participants = [
    {
        id: 12,
        name: "Alex Staff",
        url: "/search/user/12",
    },
    {
        id: 34,
        name: "Jordan Staff",
        url: null,
    },
];

test("renders labelled SOCS music participants with links and separators", () => {
    const wrapper = mount(SocsMusicParticipantMetaItem, {
        props: {
            icon: "staff",
            label: "Staff",
            participants,
        },
    });

    expect(wrapper.text()).toBe("Staff: Alex Staff, Jordan Staff");
    expect(wrapper.get("a").attributes("href")).toBe("/search/user/12");
});

test("does not render when no SOCS music participants are present", () => {
    const wrapper = mount(SocsMusicParticipantMetaItem, {
        props: {
            icon: "staff",
            label: "Staff",
            participants: [],
        },
    });

    expect(wrapper.find("li").exists()).toBe(false);
});
