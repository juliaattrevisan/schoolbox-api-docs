import { mount } from "@vue/test-utils";
import SpResults from "../sp-results.vue";
import { createTestingPinia } from "@pinia/testing";
import { useResultsStore } from "../store/spResultsStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResults, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("if there are no search results, no results should be displayed", async () => {
    expect(wrapper.find('[data-test^="sp-results-result"]').exists()).toBe(false);
});

test("if there are search results, results should be displayed", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({
        allResults: [{ id: 1, title: "test", type: "user", typeTitle: "User", _links: [], context: [] }],
    });
    expect(wrapper.get('[data-test^="sp-results-result"]').isVisible()).toBe(true);
});

test("if there are two search results, two results should be shown", async () => {
    const resultsStore = useResultsStore();
    await resultsStore.$patch({
        allResults: [
            { id: 1, title: "test", type: "user", typeTitle: "User", _links: [], context: [] },
            { id: 2, title: "test", type: "user", typeTitle: "User", _links: [], context: [] },
        ],
    });
    expect(wrapper.findAll('[data-test^="sp-results-result"]')).toHaveLength(2);
});
