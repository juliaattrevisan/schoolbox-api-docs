import { mount } from "@vue/test-utils";
import type { RubricGridCapability, RubricMarkedGridCapability } from "../../rubricMarkable/types";
import RrGridCapability from "../rr-grid-capability.vue";

/**
 * Build a minimal rubric grid capability for testing.
 * @param showCapabilityMark Whether the rubric grid says marks should show for this capability.
 * @returns A capability with no indicators, for testing the summary row alone.
 */
function makeCapability(showCapabilityMark: boolean): RubricGridCapability {
    return {
        id: 1,
        name: "Capability 1",
        maxValue: 10,
        derivedMaxValue: 10,
        showCapabilityMark,
        indicators: [],
    };
}

/**
 * Build a minimal instructor-given capability mark for testing.
 * @returns A capability mark with a fixed human-readable value.
 */
function makeCapabilityMark(): RubricMarkedGridCapability {
    return {
        id: 1,
        humanReadableMark: "8/10",
        value: "8",
        scaledMark: 8,
        indicators: [],
    };
}

/**
 * Mount rr-grid-capability with a single capability and its mark.
 * @param showCapabilityMark Whether the rubric grid says marks should show for this capability.
 * @returns The mounted component wrapper.
 */
function mountCapability(showCapabilityMark: boolean): ReturnType<typeof mount> {
    return mount(RrGridCapability, {
        props: {
            capability: makeCapability(showCapabilityMark),
            capabilityMark: makeCapabilityMark(),
            selfAssessmentCapabilityMark: null,
            levels: [],
            participantId: 1,
            showSummaryCapability: true,
        },
    });
}

describe("rr-grid-capability", () => {
    // SA-22870: capability marks were being suppressed on the markable grid via
    // capability.showCapabilityMark; the readonly grid never checked that flag at all,
    // relying solely on the page-level showSummaryCapability/showGroupTotal switch.
    // That meant stale marks left over from when a rubric had mark-per-capability
    // enabled could leak through here after the setting was later turned off.
    it("hides the capability mark summary row when the rubric grid says marks shouldn't show for this capability", () => {
        const wrapper = mountCapability(false);

        expect(wrapper.find(".rb-group-summary").exists()).toBe(false);
    });

    it("shows the capability mark summary row when the rubric grid allows it", () => {
        const wrapper = mountCapability(true);

        expect(wrapper.find(".rb-group-summary").exists()).toBe(true);
        expect(wrapper.text()).toContain("8/10");
    });
});
