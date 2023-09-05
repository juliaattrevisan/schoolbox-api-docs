import { mount } from "@vue/test-utils";
import SpResultsList from "../sp-results-list";
import { createTestingPinia } from "@pinia/testing";
import { useResultsStore } from "../../../results/store/spResultsStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResultsList, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("if there are no search results, no results should be displayed", async () => {
    expect(wrapper.find("[data-test=sp-results-list-result]").exists()).toBe(false);
});

test("if there are search results, results should be displayed", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ results: [{ id: 1, name: "test", type: "user" }] });
    expect(wrapper.get("[data-test=sp-results-list-result]").isVisible()).toBe(true);
});

test("if there are two search results, two results should be shown", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({
        results: [
            { id: 1, name: "test", type: "user" },
            { id: 2, name: "test", type: "user" },
        ],
    });
    expect(wrapper.findAll("[data-test=sp-results-list-result]")).toHaveLength(2);
});
