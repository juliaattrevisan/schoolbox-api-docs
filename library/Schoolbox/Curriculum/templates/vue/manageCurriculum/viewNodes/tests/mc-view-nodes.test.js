import { mount } from "@vue/test-utils";
import McViewNodes from "../mc-view-nodes.vue";
import { createTestingPinia } from "@pinia/testing";
import { useNodesStore } from "../../store/mcNodesStore";
import { useSearchStore } from "../../store/mcSearchStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(async () => {
    wrapper = mount(McViewNodes, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], // By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
    });
    await useNodesStore().$patch({
        nodes: {
            id: 0,
            name: "My Node",
            _usageCounts: {
                course: 0,
                unit: 0,
                assessment: 0,
                badge: 0,
            },
            children: [
                {
                    id: 1,
                    name: "My Second Node",
                    _usageCounts: {
                        course: 0,
                        unit: 0,
                        assessment: 0,
                        badge: 0,
                    },
                    children: [
                        {
                            id: 2,
                            name: "My Third Node",
                            _usageCounts: {
                                course: 0,
                                unit: 0,
                                assessment: 0,
                                badge: 0,
                            },
                        },
                    ],
                },
            ],
        },
        isLoading: false,
    });
});

test("should show only nodes two levels deep", async () => {
    expect(wrapper.findAllComponents("[data-test='view-node-name']")).toHaveLength(2);
});

test("should not show nodes while nodes is loading", async () => {
    await useNodesStore().$patch({ isLoading: true });
    expect(wrapper.find("ul").exists()).toBe(false);
});

test("should not show nodes while search is loading", async () => {
    await useSearchStore().$patch({ isLoading: true });
    expect(wrapper.find("ul").exists()).toBe(false);
});

test("should display message when search has found no matching nodes", async () => {
    await useSearchStore().$patch({ query: "test query" }); // we just simulate this as results will be empty
    expect(wrapper.element).toHaveTextContent("No Curriculum matches your current search.");
});

test("should show nodes in search results when search is active", async () => {
    await useSearchStore().$patch({
        query: "Node",
        results: [
            {
                id: 1,
                code: "C1",
                name: "Node 1",
                description: "",
                _usageCounts: { course: 0, unit: 0, assessment: 0, badge: 0 },
            },
            {
                id: 2,
                code: "C2",
                name: "Node 2",
                description: "",
                _usageCounts: { course: 0, unit: 0, assessment: 0, badge: 0 },
            },
            {
                id: 3,
                code: "C2",
                name: "Node 3",
                description: "",
                _usageCounts: { course: 0, unit: 0, assessment: 0, badge: 0 },
            },
        ],
    });
    expect(wrapper.findAllComponents("[data-test='view-node-name']")).toHaveLength(3);
});

test("should show currently selected node when search results are cleared", async () => {
    await useSearchStore().$patch({ query: "test query" }); // we just simulate this as results will be empty
    expect(wrapper.element).toHaveTextContent("No Curriculum matches your current search.");
    await wrapper.find("[data-test-clear-search]").trigger("click");
    expect(wrapper.findAllComponents("[data-test='view-node-name']")).toHaveLength(2);
});
