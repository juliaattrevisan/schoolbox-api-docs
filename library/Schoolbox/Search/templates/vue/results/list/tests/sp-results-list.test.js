import { mount } from "@vue/test-utils";
import SpResults from "../../sp-results";
import { createTestingPinia } from "@pinia/testing";
import { useSearchStore } from "../../../store/spSearchStore";
import { useResultsStore } from "../../../results/store/spResultsStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResults, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("if there are search results, results should be displayed", async () => {
    const searchStore = useSearchStore();
    await searchStore.$patch({ hasNotSearched: false });
    const resultsStore = useResultsStore();
    await resultsStore.$patch({ results: [{ id: 1, name: "test" }] });
    expect(wrapper.get("[data-test-sp-results-list]").isVisible()).toBe(true);
});

test("if there are two search results, two results should be shown", async () => {
    const searchStore = useSearchStore();
    await searchStore.$patch({ hasNotSearched: false });
    const resultsStore = useResultsStore();
    await resultsStore.$patch({
        results: [
            { id: 1, name: "test" },
            { id: 2, name: "test" },
        ],
    });
    expect(wrapper.findAll("[data-test-sp-results-list-result]")).toHaveLength(2);
});
