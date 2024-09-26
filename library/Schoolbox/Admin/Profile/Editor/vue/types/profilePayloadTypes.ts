/**
 * The data payloads for the profile page and profile editor endpoints
 */

import type { ProfileFieldSetting, ProfileFieldSettingKey, ProfileRoleType } from "./profileTypes";
import type { ProfileEditorRoleType } from "./profileEditorTypes";

export type ProfilePagePayload = {
    data: {
        roleTypes: ProfileRoleType[];
    };
};

export type ProfileEditorPayload = {
    data: {
        roleTypes: ProfileEditorRoleType[];
        fieldSettings: Record<ProfileFieldSettingKey, ProfileFieldSetting>;
    };
};
