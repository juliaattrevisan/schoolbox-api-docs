/** 
 * This is to handle any date display / manipulation within Schoolbox
 * As well as including the base package, any additional plugins that are 
 * used within Schoolbox are also added. 
 * https://day.js.org/
 */

import dayjs from 'dayjs';
import advancedFormat from 'dayjs/plugin/advancedFormat';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import relativeTime from 'dayjs/plugin/relativeTime';

//AdvancedFormat extends dayjs().format API to supply more format options: https://day.js.org/docs/en/plugin/advanced-format
dayjs.extend(advancedFormat); 
//CustomParseFormat extends dayjs() constructor to support custom formats of input strings: https://day.js.org/docs/en/plugin/custom-parse-format
dayjs.extend(customParseFormat); 
//RelativeTime adds .from .to .fromNow .toNow APIs to formats date to relative time strings (e.g. 3 hours ago): https://day.js.org/docs/en/plugin/relative-time
dayjs.extend(relativeTime); 

