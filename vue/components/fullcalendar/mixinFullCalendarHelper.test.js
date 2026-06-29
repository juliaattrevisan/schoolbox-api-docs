jest.mock("@schoolboxCalendarEvent/event-variants", () => ({ EVENT_VARIANTS: { TASK: "task" } }), { virtual: true });

import fullCalendarHelper from "./mixinFullCalendarHelper";

const getRelatedEventKey = fullCalendarHelper.methods.fullCalendarHelper_getRelatedEventKey;
const expandEventFetchRange = fullCalendarHelper.methods.fullCalendarHelper_expandEventFetchRange;
const eventOverlapsFetchRange = fullCalendarHelper.methods.fullCalendarHelper_eventOverlapsFetchRange;

describe("mixinFullCalendarHelper related event keys", () => {
    it("uses FullCalendar group classes as relation keys", () => {
        expect(getRelatedEventKey({ classNames: ["assessment", "group42"] })).toBe("group:42");
    });

    it("does not group calendar events by broad metadata fallbacks", () => {
        expect(
            getRelatedEventKey({
                classNames: [],
                extendedProps: {
                    data: {
                        links: { category: { id: 5 } },
                        timetable: { code: "ENG101" },
                        meta: { eventTypeId: 9 },
                    },
                },
            }),
        ).toBeNull();
    });
});

describe("mixinFullCalendarHelper event fetch ranges", () => {
    it("expands event fetch ranges before and after the visible calendar range", () => {
        const range = expandEventFetchRange({
            start: new Date("2026-06-18T00:00:00Z"),
            end: new Date("2026-06-19T00:00:00Z"),
        });

        expect(range.start.toISOString()).toBe("2026-05-19T00:00:00.000Z");
        expect(range.end.toISOString()).toBe("2026-09-27T00:00:00.000Z");
    });

    it("matches events that overlap the visible fetch range", () => {
        const range = {
            start: new Date("2026-06-18T00:00:00Z"),
            end: new Date("2026-06-19T00:00:00Z"),
        };

        expect(eventOverlapsFetchRange({ start: "2026-06-17T23:00:00Z", end: "2026-06-18T01:00:00Z" }, range)).toBe(
            true,
        );
        expect(eventOverlapsFetchRange({ start: "2026-06-19T00:00:00Z" }, range)).toBe(false);
    });
});
