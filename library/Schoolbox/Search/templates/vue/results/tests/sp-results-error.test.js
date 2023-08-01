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

test("if there is a load error, load error should be displayed", async () => {
    const store = useSearchStore();
    await store.$patch({ hasNotSearched: false, hasLoadError: true });
    expect(wrapper.get("[data-test-sp-results-error]").isVisible()).toBe(true);
});
