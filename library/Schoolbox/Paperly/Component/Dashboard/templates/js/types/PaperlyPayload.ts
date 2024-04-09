/**
 * The payload that comes from paperly to the dashboard.
 */

import type { FeatureItem } from "./FeatureItem";
import type { Upcoming } from "./Upcoming";

export type Item = {
    features: FeatureItem[];
    upcoming: Upcoming[];
};
