/**
 * The payload that comes from paperly to the dashboard.
 */

import type { FeatureItems } from "./FeatureItems";
import type { UpcomingItem } from "./UpcomingItem";

export type PaperlyPayload = {
    features: FeatureItems;
    upcoming: UpcomingItem[];
};
