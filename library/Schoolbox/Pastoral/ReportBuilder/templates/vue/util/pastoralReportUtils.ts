/**
 * Any utility functions for the Pastoral Report.
 */

import type { UserCC as User } from "@schoolboxLibrary/types/User";
import type { Tab, TableHeader, RowOptions, TypeSubType, RowKeys } from "../../js/types/pastoralReportTypes";
import { TabTitle } from "../../js/types/pastoralReportTypes";

/**
 * Returns the row id, currently if there's a 'user' key in rowData,
 * it'll return the user's id, otherwise it defaults to 0.
 * @returns - The id of the row, or 0 if not defined.
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
        return {
            "[type][type_id]": typeSubtype.typeId || 0,
            "[type][subtype_id]": typeSubtype.subtypeId || 0,
        };
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
    const meta = [];
    /**
     * For example: "Academic (Academic Honesty)" or "Joffrey Buckner | Wellbeing (Behaviour)"
     */
    if (selectedTab?.title === TabTitle.STUDENT_BY_TYPE) {
        const user = rowData?.user as User;
        meta.push(
            `${user ? `${user.firstName} ${user.lastName} | ` : ""} ${header.title} ${header.subTitle ? `(${header.subTitle})` : ""}`,
        );
        return meta;
    }
    /**
     * For example: "Apr 2025" or "Aug 2025 | Type: Academic (Behaviour)"
     */
    if (selectedTab?.title === TabTitle.TYPE_BY_MONTH || selectedTab?.title === TabTitle.TYPE_BY_WEEK) {
        const typeSubtype = rowData?.type_subtype as TypeSubType;
        meta.push(
            `${header.title} ${typeSubtype ? `| ${typeSubtype.type} ${typeSubtype && typeSubtype.subtype ? `(${typeSubtype.subtype})` : ""}` : ""}`,
        );
        return meta;
    }
    return [];
}
