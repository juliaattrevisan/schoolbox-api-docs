/**
 * This is to handle any date display / manipulation within Schoolbox
 * As well as including the base package, any additional plugins that are
 * used within Schoolbox are also added.
 * https://day.js.org/
 */

import dayjs from 'dayjs';
import advancedFormat from 'dayjs/plugin/advancedFormat';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import duration from 'dayjs/plugin/duration';
import relativeTime from 'dayjs/plugin/relativeTime';
import isBetween from 'dayjs/plugin/isBetween';
import arraySupport from 'dayjs/plugin/arraySupport';
import weekOfYear from 'dayjs/plugin/weekOfYear';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';

// AdvancedFormat extends dayjs().format API to supply more format options: https://day.js.org/docs/en/plugin/advanced-format
dayjs.extend(advancedFormat);
// CustomParseFormat extends dayjs() constructor to support custom formats of input strings: https://day.js.org/docs/en/plugin/custom-parse-format
dayjs.extend(customParseFormat);
// Duration adds .duration .isDuration APIs to support duration: https://day.js.org/docs/en/plugin/duration
dayjs.extend(duration);
// RelativeTime adds .from .to .fromNow .toNow APIs to formats date to relative time strings (e.g. 3 hours ago): https://day.js.org/docs/en/plugin/relative-time
dayjs.extend(relativeTime);
// IsBetween adds .isBetween() API to returns a boolean indicating if a date is between two other dates: https://day.js.org/docs/en/plugin/is-between
dayjs.extend(isBetween);
// ArraySupport extends dayjs(), dayjs.utc APIs to support array argument: https://day.js.org/docs/en/plugin/array-support#docsNav
dayjs.extend(arraySupport);
// WeekOfYear adds .week() API to returns a number indicating the Dayjs's week of the year: https://day.js.org/docs/en/plugin/week-of-year
dayjs.extend(weekOfYear);
// IsSameOrAfter adds .isSameOrAfter() API to return a boolean indicating if a date is the same or after another date: https://day.js.org/docs/en/plugin/is-same-or-after
dayjs.extend(isSameOrAfter);
