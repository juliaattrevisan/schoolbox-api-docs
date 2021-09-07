/** 
 * To have a single source of Highcharts between Vue and Twig, Highcharts is exposed here 
 * and within webpack.config.js using expose-loader is added to the global scope
 * 
 * This is for Highcharts PLUS 'more' which adds extra modules which some pages use
 * https://github.com/highcharts/highcharts-vue#importing-highcharts-modules
 * 
 * <script type="text/javascript" src="{{ CDN_PREFIX }}/js/bundle/highchartsMore.js"></script>
 * 
 */

import Highcharts from 'highcharts';
import hcMore from 'highcharts/highcharts-more';

hcMore(Highcharts);