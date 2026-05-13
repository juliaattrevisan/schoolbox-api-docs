import { mount } from "@vue/test-utils";
import BasicModal from "../basic-modal.vue";

function mountModal(props = {}, slots = {}) {
    return mount(BasicModal, {
        attachTo: document.body,
        props: {
            "is-open": true,
            hasFocusTrap: false,
            ...props,
        },
        slots: {
            default: "Modal body",
            footer: '<div data-test="modal-footer">Modal footer</div>',
            ...slots,
        },
        global: {
            stubs: {
                teleport: true,
                "sbx-icon": true,
            },
        },
    });
}

afterEach(() => {
    document.body.innerHTML = "";
});

test("renders the close button outside the clipped container", () => {
    const wrapper = mountModal();

    const frame = wrapper.get(".modal__frame");
    const container = wrapper.get(".modal__container");
    const closeButton = wrapper.get('[data-test="basic-modal-close-button"]');

    expect(frame.element.contains(closeButton.element)).toBe(true);
    expect(container.element.contains(closeButton.element)).toBe(false);
    expect(container.text()).toContain("Modal body");
    expect(container.get('[data-test="modal-footer"]').text()).toBe("Modal footer");
});

test("emits an update when the close button is clicked", async () => {
    const wrapper = mountModal();

    await wrapper.get('[data-test="basic-modal-close-button"]').trigger("click");

    expect(wrapper.emitted("update:is-open")).toEqual([[false]]);
});

test("does not render the close button when disabled", () => {
    const wrapper = mountModal({ showCloseButton: false });

    expect(wrapper.find('[data-test="basic-modal-close-button"]').exists()).toBe(false);
});
