/**
 * The payload that comes from paperly to the dashboard.
 */

import type { FeatureItem } from "./FeatureItem";
import type { UpcomingItem } from "./UpcomingItem";

export type Item = {
    features: FeatureItem[];
    upcoming: UpcomingItem[];
};
