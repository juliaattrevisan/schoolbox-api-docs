/**
 * Some Folders Category Codes appears to be pre- or post-fixed with whitespace.
 * This caused them not to match in various queries.
 */

UPDATE categoryCode SET code=TRIM(code);
