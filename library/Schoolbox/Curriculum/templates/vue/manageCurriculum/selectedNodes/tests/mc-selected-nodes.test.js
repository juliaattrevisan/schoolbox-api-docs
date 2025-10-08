import { nextTick } from "vue";
import { mount } from "@vue/test-utils";
import McSelectedNodes from "../mc-selected-nodes";
import { createTestingPinia } from "@pinia/testing";
import { useSelectedNodesStore } from "../store/mcSelectedNodesStore";

let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(McSelectedNodes, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
    });
});

test("should not display when there is no codes", () => {
    expect(wrapper.find("[data-test-nodes-container]").exists()).toBe(false);
});

test("should display when there is codes", async () => {
    await useSelectedNodesStore().$patch({
        nodes: [
            { id: 1, code: "C1", name: "Code 1", enabled: true },
            { id: 2, code: "C2", name: "Code 2", enabled: true },
        ],
    });
    expect(wrapper.findAll("[data-test-node]")).toHaveLength(2); //should be two items in the dom now
});

test("should not display when had codes but has been emptied", async () => {
    await useSelectedNodesStore().$patch({
        nodes: [
            { id: 1, code: "C1", name: "Code 1", enabled: true },
            { id: 2, code: "C2", name: "Code 2", enabled: true },
        ],
    });
    const selectedNodes = wrapper.findAllComponents("[data-test-node]");
    selectedNodes.forEach((node, index) => {
        const nodeItem = selectedNodes.at(index);
        nodeItem.find("[data-test-remove-button]").trigger("click");
    });
    await nextTick(); //need to ensure the DOM has updated first prior to checking
    const noNodes = wrapper.findAllComponents("[data-test-node]");
    expect(noNodes).toHaveLength(0); //should be no items in the dom now
});
