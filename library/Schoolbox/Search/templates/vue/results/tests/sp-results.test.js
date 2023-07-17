import { mount } from "@vue/test-utils";
import SpResults from "../sp-results";
import { createTestingPinia } from "@pinia/testing";
import { useSearchStore } from "../../store/spSearchStore";
import { useResultsStore } from "../../results/store/spResultsStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResults, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("if has not searched, should display search prompt", async () => {
    expect(wrapper.get("[data-test-sp-results-prompt]").isVisible()).toBe(true);
});

test("while searching, loading should be displayed", async () => {
    const store = useSearchStore();
    await store.$patch({ hasNotSearched: false, isSearching: true });
    expect(wrapper.get("[data-test-loading-table]").isVisible()).toBe(true);
});

test("if there is a load error, load error should be displayed", async () => {
    const store = useSearchStore();
    await store.$patch({ hasNotSearched: false, hasLoadError: true });
    expect(wrapper.get("[data-test-sp-results-error]").isVisible()).toBe(true);
});

test("if there are no search results, no results should be displayed", async () => {
    const store = useSearchStore();
    await store.$patch({ hasNotSearched: false });
    expect(wrapper.get("[data-test-sp-results-none]").isVisible()).toBe(true);
});

test("if there are search results, results should be displayed", async () => {
    const searchStore = useSearchStore();
    await searchStore.$patch({ hasNotSearched: false });
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ results: [{ id: 1, name: "test" }] });
    expect(wrapper.get("[data-test-sp-results-list]").isVisible()).toBe(true);
});
