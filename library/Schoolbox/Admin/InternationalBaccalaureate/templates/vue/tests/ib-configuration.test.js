import { mount } from "@vue/test-utils";
import { apiRequest } from "@js/util/sbx/apiUtils";
import IbConfiguration from "../ib-configuration.vue";

jest.mock("@js/util/sbx/apiUtils", () => ({
    apiRequest: jest.fn(),
}));

function deferredResponse() {
    let resolve;
    const promise = new Promise((resolvePromise) => {
        resolve = resolvePromise;
    });

    return { promise, resolve };
}

function mountConfiguration() {
    return mount(IbConfiguration, {
        props: {
            categories: [
                { id: 0, name: "general", title: "General" },
                { id: 1, name: "group-1", title: "Group 1" },
                { id: 2, name: "group-2", title: "Group 2" },
            ],
            defaultLanguage: null,
            configurationEndpoint: "",
            saveEndpoint: "/save",
            subjectsEndpoint: "/subjects/{groupId}",
            routes: {
                searchLanguages: null,
                searchClassCodes: null,
                studentCount: null,
            },
        },
        global: {
            stubs: {
                DataLoadError: true,
                IBGeneralConfig: true,
                IBSubjectTable: true,
            },
        },
    });
}

async function selectCategory(wrapper, categoryName) {
    await wrapper.find(`[data-test="ib-configuration-tab-${categoryName}"]`).trigger("click");
    await window.Vue.nextTick();
}

describe("IB configuration subject loading", () => {
    beforeEach(() => {
        apiRequest.mockReset();
        window.history.replaceState(null, "", "/");
    });

    test("clears loading and ignores an in-flight response when switching to General", async () => {
        const groupResponse = deferredResponse();
        apiRequest.mockReturnValueOnce(groupResponse.promise);
        const wrapper = mountConfiguration();

        await selectCategory(wrapper, "group-1");
        expect(wrapper.vm.$.setupState.isSubjectLoading).toBe(true);

        await selectCategory(wrapper, "general");
        expect(wrapper.vm.$.setupState.isSubjectLoading).toBe(false);

        groupResponse.resolve({
            success: true,
            data: [{ id: 1, subject: "Stale subject", level: "HL", languages: [] }],
        });
        await window.Vue.nextTick();

        expect(wrapper.vm.$.setupState.currentSubjectsFromAPI).toEqual([]);
    });

    test("keeps the latest category subjects when responses arrive out of order", async () => {
        const firstResponse = deferredResponse();
        const secondResponse = deferredResponse();
        apiRequest.mockReturnValueOnce(firstResponse.promise).mockReturnValueOnce(secondResponse.promise);
        const wrapper = mountConfiguration();

        await selectCategory(wrapper, "group-1");
        await selectCategory(wrapper, "group-2");

        const latestSubjects = [{ id: 2, subject: "Latest subject", level: "SL", languages: [] }];
        secondResponse.resolve({ success: true, data: latestSubjects });
        await window.Vue.nextTick();

        firstResponse.resolve({
            success: true,
            data: [{ id: 1, subject: "Stale subject", level: "HL", languages: [] }],
        });
        await window.Vue.nextTick();

        expect(wrapper.vm.$.setupState.currentSubjectsFromAPI).toEqual(latestSubjects);
        expect(wrapper.vm.$.setupState.isSubjectLoading).toBe(false);
    });
});
