/**
 * Any utility functions for the Pastoral Report.
 */

import type { UserCC as User } from "@schoolboxLibrary/types/User";
import type { Tab, TableHeader, RowOptions, TypeSubType, RowKeys } from "../../js/types/pastoralReportTypes";
import { TabTitle } from "../../js/types/pastoralReportTypes";

/**
 * Returns the row parameters to send to the /scope url
 * @returns - The row parameters for the /scope url.
 */
export function getRowKeys(rowData: { [key: string]: RowOptions }): RowKeys {
    // check if there's a 'user' key in rowData and return its id if it exists
    const user = rowData.user as User;
    if (user) {
        return {
            "[student_id]": user.id || 0,
        };
    }
    // otherwise, check for 'type_subtype' and return its ids if it exists
    const typeSubtype = rowData.type_subtype as TypeSubType;
    if (typeSubtype) {
        const result: RowKeys = {
            "[type][type_id]": typeSubtype.typeId || 0,
        };
        // Only add subtypeId if it exists
        if (typeSubtype.subtypeId !== null) {
            result["[type][subtype_id]"] = typeSubtype.subtypeId;
        }
        return result;
    }
    return {};
}

/**
 * Get current metadata for the current modal selection,
 * which will vary depending on the table and current cell selection
 * @param selectedTab - The currently selected tab.
 * @param header - The header information for the current cell.
 * @param rowData - The data for the current row.s
 * @returns An array of metadata strings.
 */
export function getMeta(
    selectedTab: Tab | null,
    header: TableHeader,
    rowData?: { [key: string]: RowOptions },
): string[] {
    const tabTitle = selectedTab?.title;
    if (!tabTitle) return [];
    /**
     * For example: "Academic (Academic Honesty)" or "Joffrey Buckner | Wellbeing (Behaviour)"
     */
    const studentTabs = [TabTitle.STUDENT_BY_TYPE, TabTitle.STUDENT_BY_MONTH, TabTitle.STUDENT_BY_WEEK];
    if (studentTabs.includes(tabTitle)) {
        const user = rowData?.user as User;
        return [
            `${user ? `${user.firstName} ${user.lastName} | ` : ""} ${header.title} ${header.subTitle ? `(${header.subTitle})` : ""}`,
        ];
    }
    /**
     * For example: "Apr 2025" or "Aug 2025 | Type: Academic (Behaviour)"
     */
    const typeTabs = [TabTitle.TYPE_BY_MONTH, TabTitle.TYPE_BY_WEEK];
    if (typeTabs.includes(tabTitle)) {
        const typeSubtype = rowData?.type_subtype as TypeSubType;
        return [
            `${header.title} ${typeSubtype ? `| ${typeSubtype.type} ${typeSubtype && typeSubtype.subtype ? `(${typeSubtype.subtype})` : ""}` : ""}`,
        ];
    }
    return [];
}
