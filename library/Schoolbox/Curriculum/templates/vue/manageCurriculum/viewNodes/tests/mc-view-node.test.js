import { mount } from "@vue/test-utils";
import McViewNode from "../mc-view-node.vue";
import { createTestingPinia } from "@pinia/testing";
import { useViewNodesStore } from "../store/mcViewNodesStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(async () => {
    wrapper = mount(McViewNode, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], // By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            node: {
                id: 0,
                name: "My Node",
                enabled: true,
            },
            depth: 0,
        },
    });
});

test("should hide disabled nodes when showDisabled is false", async () => {
    await useViewNodesStore().$patch({ showDisabled: false });
    await wrapper.setProps({
        node: {
            ...wrapper.props.node,
            enabled: false,
        },
    });
    expect(wrapper.find("li").exists()).toBe(false);
});
