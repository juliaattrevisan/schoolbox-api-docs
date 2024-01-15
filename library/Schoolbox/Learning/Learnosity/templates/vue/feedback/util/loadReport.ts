/**
 * To load the reports data and pass it to the other stores
 */

import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import { addTargetsToMarks, disableTextAreas } from "@schoolboxLearnosity/js/util/domUtils";
import { useStudentStore } from "../store/qfStudentStore";

/**
 * To load the reports data and pass it to the other stores
 * @param {object} initOptions - the report init options
 */
export function loadReport(initOptions: object) {
    const reportId = initOptions.request.reports[0].id as string; // To link the report to the student
    const reportType = initOptions.request.reports[0].type as string;
    const displayScores = initOptions.request.reports[0].display_scores as boolean;

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
                    const attemptReport = reportsApp.getReport(reportId);
                    // itemsApi only spawns if you used session-detail-by-item
                    attemptReport.on("ready:itemsApi", (itemsApp) => {
                        // Load the items for the student
                        itemsApp.getItems(($items: object) => {
                            const items: StudentItem[] = [];
                            // grab each item object and push it into the items array
                            Object.values($items).forEach((item: StudentItem) => {
                                items.push(item);
                            });
                            useStudentStore().addItems(items);

                            if (displayScores) {
                                disableTextAreas(reportId);
                            }
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
    const reportsApp = LearnosityReports.init(initOptions, reportCallback);
}
