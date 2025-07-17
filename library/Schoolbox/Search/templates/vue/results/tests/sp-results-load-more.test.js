import { mount } from "@vue/test-utils";
import SpResultsLoadMore from "../sp-results-load-more.vue";
import { createTestingPinia } from "@pinia/testing";
import { useResultsStore } from "../store/spResultsStore";

// TODO: These tests are broken.  Fix them.

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResultsLoadMore, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("do not show load more button when no more results to load", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ hasLoadedAllResults: true });
    expect(wrapper.find('[data-test="sp-results-load-more-button"]').exists()).toBe(false);
});

test("show all results loaded when all results have been loaded", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ hasLoadedAllResults: true });
    expect(wrapper.find('[data-test="sp-results-load-more-all-loaded"]').exists()).toBe(true);
});
