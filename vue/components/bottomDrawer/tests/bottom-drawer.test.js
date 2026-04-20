import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import BottomDrawer from "../bottom-drawer.vue";

const rootRect = {
    width: 1200,
    height: 0,
    x: 80,
    y: 0,
    top: 0,
    right: 1280,
    bottom: 0,
    left: 80,
    toJSON: () => null,
};

const drawerRect = {
    width: 1200,
    height: 64,
    x: 80,
    y: 0,
    top: 0,
    right: 1280,
    bottom: 64,
    left: 80,
    toJSON: () => null,
};

class ResizeObserverMock {
    observe() {}

    disconnect() {}
}

function mountDrawer(props = {}) {
    const wrapper = mount(BottomDrawer, {
        attachTo: document.body,
        props: {
            showDrawer: true,
            ...props,
        },
        slots: {
            default: "Drawer content",
        },
    });

    wrapper.element.getBoundingClientRect = jest.fn(() => rootRect);
    wrapper.get(".bottom-drawer-container").element.getBoundingClientRect = jest.fn(() => drawerRect);
    wrapper.vm.updatePosition();

    return wrapper;
}

beforeEach(() => {
    global.ResizeObserver = ResizeObserverMock;
    document.body.innerHTML = '<div id="container"></div>';
});

test("applies container offset while the drawer is fixed and visible", () => {
    mountDrawer();

    const containerEl = document.getElementById("container");
    expect(containerEl.style.getPropertyValue("--bottom-drawer-offset")).toBe("64px");
    expect(containerEl.getAttribute("data-has-bottom-drawer")).toBe("true");
});

test("clears container offset when the drawer hides", async () => {
    const wrapper = mountDrawer();

    await wrapper.setProps({ showDrawer: false });
    await nextTick();

    const containerEl = document.getElementById("container");
    expect(containerEl.style.getPropertyValue("--bottom-drawer-offset")).toBe("");
    expect(containerEl.hasAttribute("data-has-bottom-drawer")).toBe(false);
});

test("does not apply container offset while the drawer is static", () => {
    mountDrawer({
        isAlwaysAnchored: false,
        unanchorWidth: 2000,
    });

    const containerEl = document.getElementById("container");
    expect(containerEl.style.getPropertyValue("--bottom-drawer-offset")).toBe("");
    expect(containerEl.hasAttribute("data-has-bottom-drawer")).toBe(false);
});

test("clears container offset on unmount", () => {
    const wrapper = mountDrawer();
    const containerEl = document.getElementById("container");

    wrapper.unmount();

    expect(containerEl.style.getPropertyValue("--bottom-drawer-offset")).toBe("");
    expect(containerEl.hasAttribute("data-has-bottom-drawer")).toBe(false);
});
