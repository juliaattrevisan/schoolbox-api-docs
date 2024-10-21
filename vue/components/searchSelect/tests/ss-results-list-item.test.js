import { mount } from "@vue/test-utils";
import SsResultsListItem from "../ss-results-list-item.vue";

const defaultProps = {
    query: "",
    selectedItems: [],
    result: { id: 1, name: "Result 1" },
    isSingleSelect: false,
    index: 0,
    activeIndex: 0,
};

test("result can be added in", async () => {
    const wrapper = mount(SsResultsListItem, {
        props: {
            ...defaultProps,
        },
    });
    const searchResult = wrapper.get(`[data-test="ss-results-list-item-checkbox-1"]`);
    searchResult.trigger("click"); // add the search result to the selected items
    const addEvent = wrapper.emitted("add"); // validate the event has been emitted
    expect(addEvent[0]).toEqual([[{ id: 1, name: "Result 1" }]]); // validate the event has the correct payload
});

test("when isSingleSelect, should show radio not checkbox input", async () => {
    const wrapper = mount(SsResultsListItem, {
        props: {
            ...defaultProps,
            isSingleSelect: true,
        },
    });
    expect(wrapper.find(`[data-test="ss-results-list-item-radio-1"]`).exists()).toBe(true); // validate the radio input is visible
    expect(wrapper.find(`[data-test="ss-results-list-item-checkbox-1"]`).exists()).toBe(false); // validate the checkbox input is not visible
});

test("if result already selected, remove is triggered", async () => {
    const wrapper = mount(SsResultsListItem, {
        props: {
            ...defaultProps,
            selectedItems: [{ id: 1, name: "Result 1" }],
        },
    });
    const searchResult = wrapper.get(`[data-test="ss-results-list-item-checkbox-1"]`);
    searchResult.trigger("click"); // remove the search result from the selected items
    const removeEvent = wrapper.emitted("remove"); // validate the event has been emitted
    expect(removeEvent[0]).toEqual([[{ id: 1, name: "Result 1" }]]); // validate the event has the correct payload
});
