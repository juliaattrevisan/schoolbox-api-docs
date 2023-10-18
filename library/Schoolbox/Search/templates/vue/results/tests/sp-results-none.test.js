import { mount } from "@vue/test-utils";
import SpResults from "../sp-results";
import { createTestingPinia } from "@pinia/testing";
import { useSearchStore } from "../../store/spSearchStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResults, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("if there are no search results, no results should be displayed", async () => {
    const store = useSearchStore();
    await store.$patch({ hasNotSearched: false });
    expect(wrapper.get('[data-test="sp-results-none"]').isVisible()).toBe(true);
});
