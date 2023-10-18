import { mount } from "@vue/test-utils";
import SpResultsHeader from "../sp-results-header";
import { createTestingPinia } from "@pinia/testing";
import { useSearchStore } from "../../store/spSearchStore";
import { useQueryStore } from "../../store/spQueryStore";
import { useResultsStore } from "../../results/store/spResultsStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(SpResultsHeader, {
        global: {
            plugins: [createTestingPinia()],
        },
    });
});

test("header should not be visible until have searched", async () => {
    expect(wrapper.find('[data-test="sp-results-header"]').exists()).toBe(false);
});

test("header should be visible once have searched", async () => {
    await useSearchStore().$patch({ hasNotSearched: false });
    expect(wrapper.find('[data-test="sp-results-header"]').exists()).toBe(true);
});

test("header should contain the query text once searched", async () => {
    await useSearchStore().$patch({ hasNotSearched: false });
    await useQueryStore().$patch({ activeQuery: "whoop" });
    expect(wrapper.get('[data-test="sp-results-header"]').wrapperElement).toHaveTextContent("No results for whoop");
    await useResultsStore().$patch({ results: [{ title: "test" }] });
    expect(wrapper.get('[data-test="sp-results-header"]').wrapperElement).toHaveTextContent(
        "Showing results for whoop"
    );
});
