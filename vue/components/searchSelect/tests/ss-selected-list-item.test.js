import { mount } from "@vue/test-utils";
import SsSelectedListItem from "../ss-selected-list-item.vue";

const defaultProps = {
    item: { id: 1, name: "Item 1" },
    isSingleSelect: false,
};

test("item can be removed", async () => {
    const wrapper = mount(SsSelectedListItem, {
        props: {
            ...defaultProps,
        },
    });
    const removeButton = wrapper.get(`[data-test="search-select-selected-item-remove-1"]`);
    removeButton.trigger("click"); // add the search result to the selected items
    const removeEvent = wrapper.emitted("remove"); // validate the event has been emitted
    expect(removeEvent[0]).toEqual([{ id: 1, name: "Item 1" }]); // validate the event has the correct payload
});

test("item can be selected", async () => {
    const wrapper = mount(SsSelectedListItem, {
        props: {
            ...defaultProps,
        },
    });
    const selectButton = wrapper.get(`[data-test="search-select-selected-item-select-1"]`);
    selectButton.trigger("click"); // add the search result to the selected items
    const selectEvent = wrapper.emitted("select"); // validate the event has been emitted
    expect(selectEvent[0]).toEqual([{ id: 1, name: "Item 1" }]); // validate the event has the correct payload
});
