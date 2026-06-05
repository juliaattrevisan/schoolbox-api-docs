import { mount } from "@vue/test-utils";
import AiUsageLog from "../ai-usage-log.vue";
import { AiFeature, AiLogFormat } from "../payload";
import { apiRequest } from "@js/util/sbx/apiUtils";

jest.mock("@js/util/sbx/apiUtils", () => ({
    apiRequest: jest.fn(),
}));

const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0));

const pagination = {
    pagination: {
        current: 1,
        limit: 30,
        pages: 1,
        count: 1,
        total: 1,
    },
};

const user = {
    id: 1,
    fullName: "Ada Lovelace",
    _links: {
        profile: "/user/1",
    },
};

const session = {
    id: "session-1",
    feature: AiFeature.DIALOG_AGENT,
    logFormat: AiLogFormat.AGENT_CHAT,
    createdAt: "2026-01-01T00:00:00+00:00",
    createdBy: user,
    returnCode: 200,
    _routes: {
        getLog: "/admin/openai/usage/session-1",
    },
};

const sessionLog = [
    {
        id: 1,
        sessionId: "session-1",
        createdAt: "2026-01-01T00:00:00+00:00",
        createdBy: user,
        feature: AiFeature.DIALOG_AGENT,
        logFormat: AiLogFormat.AGENT_CHAT,
        data: {
            input: {
                createdAt: "2026-01-01T00:00:00+00:00",
                type: "request",
                data: "Summarise this interaction.",
            },
            output: {
                createdAt: "2026-01-01T00:00:01+00:00",
                type: "response",
                data: "Summary response.",
            },
        },
    },
];

function mountComponent() {
    window.dayjs = jest.fn(() => ({
        format: () => "1 Jan 2026, 11:00 AM",
    }));
    window.dateDisplayFormats = {
        dayjsDatetime: "D MMM YYYY, h:mm A",
    };
    window.print = jest.fn();

    return mount(AiUsageLog, {
        props: {
            routeSearch: "/admin/openai/usage/search",
            features: {
                [AiFeature.DIALOG_AGENT]: {
                    title: "Dialog Agent",
                },
            },
        },
        global: {
            stubs: {
                SearchSelect: true,
                DateRange: true,
                UserPhoto: true,
                RelativeTime: true,
                PaginatorButtons: true,
                AulAgentChat: {
                    template: '<div data-test="agent-chat-viewer">Printable chat transcript</div>',
                },
            },
        },
    });
}

describe("AiUsageLog", () => {
    beforeEach(() => {
        apiRequest.mockReset();
    });

    test("prints the selected AI log from the viewer actions menu", async () => {
        apiRequest
            .mockResolvedValueOnce({
                success: true,
                data: {
                    data: [session],
                    metadata: pagination,
                },
            })
            .mockResolvedValueOnce({
                success: true,
                data: sessionLog,
            });

        const wrapper = mountComponent();
        await flushPromises();

        await wrapper.get('button[aria-label="View session details"]').trigger("click");
        await flushPromises();
        await wrapper.vm.$nextTick();

        const printAction = wrapper.get('[data-test="ai-usage-log-print-session"]');
        await printAction.trigger("click");

        expect(window.print).toHaveBeenCalledTimes(1);
    });
});
