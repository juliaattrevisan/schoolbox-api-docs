import { mount } from "@vue/test-utils";
import SpResultsListLoadMore from "../sp-results-list-load-more.vue";
import { createTestingPinia } from "@pinia/testing";
import { useResultsStore } from "../../store/spResultsStore";

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
    expect(wrapper.find('[data-test="sp-results-load-more-button"]').exists()).toBe(false);
});

test("show all results loaded when all results have been loaded", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ hasLoadedAllResults: true });
    expect(wrapper.find('[data-test="sp-results-load-more-all-loaded"]').exists()).toBe(true);
});
