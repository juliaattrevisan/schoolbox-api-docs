import { mount } from "@vue/test-utils";
import SsSelectedListItem from "../ss-selected-item.vue";

const defaultProps = {
    item: { id: 1, name: "Item 1" },
    isSingleSelect: false,
    canSelectItem: false,
};

test("item can be removed", async () => {
    const wrapper = mount(SsSelectedListItem, {
        props: {
            ...defaultProps,
        },
    });
    const removeButton = wrapper.get(`[data-test="ss-selected-list-item-remove-1"]`);
    removeButton.trigger("click"); // remove the item from the selected items
    const removeEvent = wrapper.emitted("remove"); // validate the event has been emitted
    expect(removeEvent[0]).toEqual([{ id: 1, name: "Item 1" }]); // validate the event has the correct payload
});

test("item can be selected if canSelectItem is true", async () => {
    const wrapper = mount(SsSelectedListItem, {
        props: {
            ...defaultProps,
            canSelectItem: true,
        },
    });
    const selectButton = wrapper.get(`[data-test="ss-selected-item-select-1"]`);
    selectButton.trigger("click"); // select the item
    const selectEvent = wrapper.emitted("select"); // validate the event has been emitted
    expect(selectEvent[0]).toEqual([{ id: 1, name: "Item 1" }]); // validate the event has the correct payload
});

test("item cant be selected if canSelectItem is false", async () => {
    const wrapper = mount(SsSelectedListItem, {
        props: {
            ...defaultProps,
        },
    });
    expect(wrapper.find(`[data-test="ss-selected-item-select-1"]`).exists()).toBe(false);
});
