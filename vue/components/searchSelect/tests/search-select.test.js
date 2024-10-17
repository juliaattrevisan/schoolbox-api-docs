import { mount, flushPromises } from "@vue/test-utils";
import axios from "axios";
import { timeout } from "@js/util/jsUtils";
import { useSelected } from "@schoolboxVue/composables/store/useSelected";
import SearchSelect from "../search-select.vue";

test("selected items are visible", async () => {
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: [
                { id: 1, name: "Item 1" },
                { id: 2, name: "Item 2" },
                { id: 3, name: "Item 3" },
            ],
        },
    });
    expect(wrapper.findAll('[data-test^="search-select-selected-item-container-"]')).toHaveLength(3);
    const selectedItem = wrapper.get(`[data-test="search-select-selected-item-container-1"]`);
    expect(selectedItem.html()).toContain("Item 1");
});

test("selected items can be removed", async () => {
    const { selected, add, remove } = useSelected();
    const itemToRemove = { id: 2, name: "Item 2" };
    add([{ id: 1, name: "Item 1" }, itemToRemove, { id: 3, name: "Item 3" }]);
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: selected.value,
        },
    });
    wrapper.get(`[data-test="search-select-selected-item-remove-2"]`).trigger("click");
    const removeEvent = wrapper.emitted("remove"); // validate the event has been emitted
    expect(removeEvent[0]).toEqual([[itemToRemove]]); // validate the event has the correct payload
    remove(itemToRemove); // remove the item from the selected items
    expect(selected.value).toHaveLength(2); // validate the item has been removed
    await wrapper.setProps({ selectedItems: selected.value }); // update the selected items (this is needed because the prop is apparent not reactive in this context)
    expect(wrapper.findAll('[data-test^="search-select-selected-item-container-"]')).toHaveLength(2);
});

const mockSearchList = [
    { id: 4, title: "Search result 1" },
    { id: 5, title: "Search result 2" },
];

test("search results can be added", async () => {
    const { selected, add } = useSelected();
    add([{ id: 1, name: "Item 1" }]);
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: selected.value,
        },
    });

    jest.spyOn(axios, "get").mockResolvedValue(mockSearchList);

    const searchInput = wrapper.get(`[data-test="search-select-input"]`);
    searchInput.setValue("search this");
    // Let's assert that we've called axios.get the right amount of times and
    // with the right parameters.
    await timeout(1000);
    expect(axios.get).toHaveBeenCalledTimes(1);
    // expect(axios.get).toHaveBeenCalledWith("/api/posts");
});
