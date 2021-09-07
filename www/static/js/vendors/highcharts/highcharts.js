/** 
 * To have a single source of Highcharts between Vue and Twig, Highcharts is exposed here 
 * and within webpack.config.js using expose-loader is added to the global scope
 * This way Highcharts can also be accessed with:
 * <script type="text/javascript" src="{{ CDN_PREFIX }}/js/bundle/highcharts.js"></script>
 *
 */

import Highcharts from 'highcharts';