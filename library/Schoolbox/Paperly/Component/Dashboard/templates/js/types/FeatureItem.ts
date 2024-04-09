/**
 * The list of further links available depending on the user's role.
 */

type FeatureItemKey = "Forms" | "Excursions" | "PTI";

export type FeatureItem = {
    [key in FeatureItemKey]: string; // the string represents the url to load in the iframe
};
