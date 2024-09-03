import { mount } from "@vue/test-utils";
import McViewNodeCheckbox from "../mc-view-node-checkbox";
import { createTestingPinia } from "@pinia/testing";
import { useSelectedNodesStore } from "../../selectedNodes/store/mcSelectedNodesStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(McViewNodeCheckbox, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            node: {
                id: 5,
                name: "My Node",
                children: [],
            },
            isActive: false,
        },
    });
});

test("is disabled when prop isActive is true", async () => {
    await wrapper.setProps({ isActive: true });
    expect(wrapper.find("input").element).toBeDisabled();
});

test("is checked when in selected nodes store", async () => {
    await useSelectedNodesStore().$patch({
        nodes: [
            {
                id: 5,
                name: "My Node",
            },
        ],
    });
    expect(wrapper.find("input").element).toBeChecked();
});

test("is disabled and also checked when a child of a selected node", async () => {
    await useSelectedNodesStore().$patch({
        childNodes: [
            {
                id: 5,
                name: "My Node",
            },
        ],
    });
    expect(wrapper.find("input").element).toBeDisabled();
    expect(wrapper.find("input").element).toBeChecked();
});
