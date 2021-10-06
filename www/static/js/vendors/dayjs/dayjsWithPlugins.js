/** 
 * This is to handle any date display / manipulation within Schoolbox
 * As well as including the base package, any additional plugins that are 
 * used within Schoolbox are also added. 
 */

import dayjs from 'dayjs';
import advancedFormat from 'dayjs/plugin/advancedFormat';

dayjs.extend(advancedFormat);