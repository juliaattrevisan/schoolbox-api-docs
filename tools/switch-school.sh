#!/bin/bash
# Switch school from command line. Now just an alias for school.sh, but kept for
# backward compatibility.
# Usage:
#       tools/switch-school.sh [school]
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au

DIR="$( cd "$( dirname "$0" )" && pwd )"
${DIR}/school.sh $*
