/**
 * Events have a few different variants.
 * Each has display variations that need to be considered.
 */

// These constants should match server-side constants defined in Calendar/Event.php.
export const EVENT_VARIANTS = Object.freeze({
    EVENT: "event",
    TASK: "task",
    BOOKING: "booking",
});
