import { mount } from "@vue/test-utils";
import SearchSelect from "../search-select.vue";

beforeEach(() => {
    document.body.innerHTML = `
        <div id="container">
            <div id="app"></div>
        </div>
    `;
});

test("selected items are visible", async () => {
    const wrapper = mount(SearchSelect, {
        attachTo: document.getElementById("app"),
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: [
                { id: 1, name: "Item 1" },
                { id: 2, name: "Item 2" },
            ],
        },
    });
    await wrapper.vm.$nextTick();
    const selectedItem = wrapper.get(`[data-test="search-select-selected-item-1"]`);
    expect(selectedItem.html()).toContain("Item 1");
});

// test("when clicked again content is hidden", async () => {
//     const wrapper = mount(SbxPopOut, {
//         attachTo: document.getElementById("app"),
//         props: {
//             contentId: "my-content",
//         },
//     });
//     await wrapper.vm.$nextTick();
//     wrapper.get(`[data-test="sbx-pop-out-my-content-button"]`).trigger("click");
//     wrapper.get(`[data-test="sbx-pop-out-my-content-button"]`).trigger("click");
//     const content = document.querySelector("#my-content");
//     expect(content.style.display).toBe("none");
// });

// test("button has text when provided", async () => {
//     const wrapper = mount(SbxPopOut, {
//         attachTo: document.getElementById("app"),
//         props: {
//             contentId: "my-content",
//             buttonText: "Open My Content",
//         },
//     });
//     await wrapper.vm.$nextTick();
//     const button = wrapper.get(`[data-test="sbx-pop-out-my-content-button"]`);
//     expect(button.html()).toContain("Open My Content");
// });

// // moveTo tests
// test("content moves to new parent when moveTo is specified by a querySelector", async () => {
//     document.body.innerHTML += `<div id="move-to-target"></div>`;
//     const wrapper = mount(SbxPopOut, {
//         attachTo: document.getElementById("app"),
//         props: {
//             contentId: "my-content",
//             moveTo: "#move-to-target",
//         },
//     });
//     await wrapper.vm.$nextTick();

//     const content = document.querySelector("#my-content");
//     expect(content.parentElement.id).toBe("move-to-target");
//     wrapper.unmount();
// });

// test("content moves to new parent when moveTo specified by an element reference", async () => {
//     document.body.innerHTML += `<div id="move-to-target"></div>`;
//     const moveToElement = document.getElementById("move-to-target");
//     const wrapper = mount(SbxPopOut, {
//         attachTo: document.getElementById("app"),
//         props: {
//             contentId: "my-content",
//             moveTo: moveToElement,
//         },
//     });
//     await wrapper.vm.$nextTick();

//     const content = document.querySelector("#my-content");
//     expect(content.parentElement).toBe(moveToElement);
//     wrapper.unmount();
// });

// test("content moves back to original parent on unmount if moveTo is was specified", async () => {
//     document.body.innerHTML += `<div id="move-to-target"></div>`;
//     const moveToElement = document.getElementById("move-to-target");
//     const originalParent = document.getElementById("app");
//     const wrapper = mount(SbxPopOut, {
//         attachTo: originalParent,
//         props: {
//             contentId: "my-content",
//             moveTo: moveToElement,
//         },
//     });
//     await wrapper.vm.$nextTick();

//     // Confirm it moved to new parent first
//     let content = document.querySelector("#my-content");
//     expect(content.parentElement).toBe(moveToElement);

//     wrapper.unmount();

//     // Now, the content should be back to its original parent
//     content = document.querySelector("#my-content");
//     expect(content.parentElement.id).toBe(originalParent.parentElement.id);
// });
