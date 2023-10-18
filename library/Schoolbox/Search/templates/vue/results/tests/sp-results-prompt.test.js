import { mount } from "@vue/test-utils";
import SpResults from "../sp-results";
import { createTestingPinia } from "@pinia/testing";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResults, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("if has not searched, should display search prompt", async () => {
    expect(wrapper.get('[data-test="sp-results-prompt"]').isVisible()).toBe(true);
});
