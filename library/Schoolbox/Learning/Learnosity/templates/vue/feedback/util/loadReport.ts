/**
 * To load the reports data and pass it to the other stores
 */

import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import { addTargetsToMarks, disableTextAreas } from "@schoolboxLearnosity/js/util/domUtils";
import type { LearnosityReportInitOptions } from "@schoolboxLearnosity/vue/learnosityReportTypes";
import { useStudentStore } from "../store/qfStudentStore";

/**
 * To load the reports data and pass it to the other stores
 * @param {LearnosityReportInitOptions} initOptions - the report init options
 * @param {boolean} hasMarkVisibility - whether the report has mark visibility
 */
export function loadReport(initOptions: LearnosityReportInitOptions, hasMarkVisibility: boolean) {
    const reportId = initOptions.request.reports[0].id; // To link the report to the student
    const reportType = initOptions.request.reports[0].type;

    let reportCallback = null;

    /**
     * Every report gets its own callback; this is because the readyListeners()
     * don't have any idea which report they are in, which means we can't tell
     * when a report has been fully loaded to the point where we can hide all
     * their divs, because we only want to show one div at a time per report.
     */
    switch (reportType) {
        case "session-detail-by-item":
            reportCallback = {
                readyListener: () => {
                    // @ts-expect-error -- reportsApp is global but untyped, this should be investigated at some point to determine if we can get this from learnosity
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-use-before-define
                    const attemptReport = (reportsApp as unknown).getReport(reportId);
                    // itemsApi only spawns if you used session-detail-by-item
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                    attemptReport.on("ready:itemsApi", (itemsApp: unknown) => {
                        // Load the items for the student
                        // @ts-expect-error -- itemsApp is global but untyped, this should be investigated at some point to determine if we can get this from learnosity
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                        itemsApp.getItems(($items: object) => {
                            const items: StudentItem[] = [];
                            // grab each item object and push it into the items array
                            Object.values($items).forEach((item: StudentItem) => {
                                items.push(item);
                            });
                            useStudentStore().addItems(items);

                            if (hasMarkVisibility) {
                                addTargetsToMarks(reportId);
                            }
                            disableTextAreas(reportId);
                        });

                        // to notify the report has loaded
                        useStudentStore().reportLoaded();
                    });
                },
            };
            break;

        default:
            console.error(`Unknown report type '${reportType}'.`);
    }
    // Initialise reports for each attempt
    // @ts-expect-error -- LearnosityReports is global but untyped, this should be investigated at some point to determine if we can get this from learnosity
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call
    const reportsApp = (LearnosityReports as unknown).init(initOptions, reportCallback);
}
