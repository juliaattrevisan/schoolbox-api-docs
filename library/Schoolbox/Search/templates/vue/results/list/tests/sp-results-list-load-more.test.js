import { mount } from "@vue/test-utils";
import SpResultsListLoadMore from "../sp-results-list-load-more.vue";
import { createTestingPinia } from "@pinia/testing";
import { useResultsStore, RESULT_LIMIT } from "../../store/spResultsStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResultsListLoadMore, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("do not show load more button when no more results to load", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ results: [{ id: 1, name: "test" }] });
    expect(wrapper.find("[data-sp-results-list-load-more-button]").exists()).toBe(false);
});

test("show load more button when more results to load", async () => {
    const resultsStore = useResultsStore();
    const results = [];
    // push to the result limit to suggest there could be more results to load
    for (let i = 0; i < RESULT_LIMIT; i++) {
        results.push({ id: i, name: "test" });
    }
    await resultsStore.$patch({ results: results });
    expect(wrapper.find("[data-sp-results-list-load-more-button]").exists()).toBe(true);
});

test("show all results loaded when all results have been loaded", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ hasLoadedAllResults: true });
    expect(wrapper.find("[data-sp-results-list-load-more-all-loaded]").exists()).toBe(true);
});
