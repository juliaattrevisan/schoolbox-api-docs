import { mount } from "@vue/test-utils";
import McViewNodeName from "../mc-view-node-name";
import { createTestingPinia } from "@pinia/testing";
import { useSearchStore } from "../../store/mcSearchStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(McViewNodeName, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            node: {
                id: 0,
                name: "My Node",
                children: [
                    {
                        id: 2,
                        name: "My Second Node",
                        children: [],
                    },
                ],
            },
            depth: 1,
            title: "My Node",
            isActive: false,
        },
    });
});

test("should be clickable when node has children", async () => {
    expect(wrapper.find("button").exists()).toBe(true);
});

test("should not be clickable when node has no children", async () => {
    await wrapper.setProps({
        node: {
            id: 0,
            name: "My Node",
            children: [],
        },
    });
    expect(wrapper.find("button").exists()).toBe(false);
});

test("should not be clickable when is the root node", async () => {
    await wrapper.setProps({ depth: 0 });
    expect(wrapper.find("button").exists()).toBe(false);
});

test("is not disabled when is not updating or adding", async () => {
    expect(wrapper.find("button").element).not.toBeDisabled();
});

test("is not disabled when search is active", async () => {
    await useSearchStore().$patch({ query: "test query" });
    expect(wrapper.find("button").element).not.toBeDisabled();
});

test("is disabled when updating or adding another node", async () => {
    await wrapper.setProps({ isActive: true });
    expect(wrapper.find("button").element).toBeDisabled();
});
