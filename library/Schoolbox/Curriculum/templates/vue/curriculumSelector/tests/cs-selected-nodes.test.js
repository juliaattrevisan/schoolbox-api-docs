import { nextTick } from "vue";
import { mount } from "@vue/test-utils";
import CsSelectedNodes from "../cs-selected-nodes";
import { createTestingPinia } from "@pinia/testing";
import { useStore } from "../store/curriculumSelectorStore";
import { useSelectedNodesStore } from "../store/csSelectedNodesStore";

let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSelectedNodes, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
    });
});

test("should not display when there is no codes", () => {
    expect(wrapper.find("[data-test-nodes-container]").exists()).toBe(false);
});

test("should display when there is codes", async () => {
    const selectedNodesStore = useSelectedNodesStore();
    await selectedNodesStore.add({
        id: 1,
        code: "C1",
        name: "Code 1",
        description: "",
        _usageCounts: { course: 0, unit: 0, assessment: 0, badge: 0 },
    });
    await selectedNodesStore.add({
        id: 2,
        code: "C2",
        name: "Code 2",
        description: "",
        _usageCounts: { course: 0, unit: 0, assessment: 0, badge: 0 },
    });
    expect(wrapper.findAll("[data-test-node]")).toHaveLength(2); //should be two items in the dom now
});

test("should not display when had codes but has been emptied", async () => {
    const store = useStore();
    store.$patch({ isReadOnly: false });
    const selectedNodesStore = useSelectedNodesStore();
    await selectedNodesStore.add({
        id: 1,
        code: "C1",
        name: "Code 1",
        description: "",
        _usageCounts: { course: 0, unit: 0, assessment: 0, badge: 0 },
    });
    await selectedNodesStore.add({
        id: 2,
        code: "C2",
        name: "Code 2",
        description: "",
        _usageCounts: { course: 0, unit: 0, assessment: 0, badge: 0 },
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
