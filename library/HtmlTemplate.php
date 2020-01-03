<?php

/**
 * This code that was derived from the original PHPLIB Template class
 * is copyright by Kristian Koehntopp, NetUSE AG and was released
 * under the LGPL.
 *
 * PHP Versions 4 and 5
 *
 * @category HTML
 * @package  HTML_Template_PHPLIB
 * @author   Kristian Koehntopp <kris@koehntopp.de>
 * @author   Bjoern Schotte <schotte@mayflower.de>
 * @author   Martin Jansen <mj@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version  CVS: $Id: PHPLIB.php,v 1.9 2008/03/03 18:33:54 cweiske Exp $
 * @link     http://pear.php.net/package/HTML_Template_PHPLIB
 */

/**
 * Converted PHPLIB Template class
 *
 * For those who want to use PHPLIB's fine template class,
 * here's a PEAR conforming class with the original PHPLIB
 * template code from phplib-stable CVS. Original author
 * was Kristian Koehntopp <kris@koehntopp.de>
 *
 * @category HTML
 * @package  HtmlTemplate
 * @author   Bjoern Schotte <schotte@mayflower.de>
 * @author   Martin Jansen <mj@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link     http://pear.php.net/package/HTML_Template_PHPLIB
 */
class HtmlTemplate
{
    // Variable behaviour constants

    const VARIABLE_KEEP = 'keep';
    const VARIABLE_REMOVE = 'remove';
    const VARIABLE_COMMENT = 'comment';

    const HALT_YES = 'yes';
    const HALT_NO = 'no';
    const HALT_RETURN = 'return';
    const HALT_REPORT = 'report';

    /** @var bool If set, echo assignments */
    var $debug = false;

    /** @var array $file $file[handle] = 'filename'; */
    var $file = [];

    /**  @var array $file_fallbacks fallback paths that should be defined in a child class */
    var $file_fallbacks = [];

    /** @var string $root Relative filenames are relative to this pathname */
    var $root = '';

    /** @var array $_varKeys $_varKeys[key] = 'key' */
    var $_varKeys = [];

    /** @var array $_varVals $_varVals[key] = 'value'; */
    var $_varVals = [];

    /** @var string $unknowns Behaviour for unknown variables */
    var $unknowns = self::VARIABLE_KEEP;

    /** @var string $haltOnError Behaviour on error */
    var $haltOnError = self::HALT_REPORT;

    /** @var string $_lastError The last error message is retained here @see halt */
    var $_lastError = '';

    /**
     * Constructor
     *
     * @param string $root Template root directory
     * @param string $unknowns How to handle unknown variables
     * @param array|string $fallback Fallback paths
     * @access public
     */
    function __construct($root = '.', $unknowns = '', $fallback = '')
    {
        $this->setRoot($root);
        if ($unknowns !== '') {
            $this->setUnknowns($unknowns);
        }
        if (is_array($fallback)) {
            $this->file_fallbacks = $fallback;
        }
    }

    /**
     * Clears a variable (sets its content to "")
     *
     * @param array|string $var Variable name to clear, or array name
     * @return void
     * @access public
     */
    function clearVar($var)
    {
        if (is_array($var)) {
            foreach ($var as $varname) {
                $this->setVar($varname, '');
            }
        } else {
            $this->setVar($var, '');
        }
    }

    /**
     * Unsets a variable completely
     *
     * @param array|string $var Variable name to clear, or array of names
     * @return void
     * @access public
     */
    function unsetVar($var)
    {
        if (is_array($var)) {
            foreach ($var as $varname) {
                unset($this->_varKeys[$varname]);
                unset($this->_varVals[$varname]);
            }
        } else {
            unset($this->_varKeys[$var]);
            unset($this->_varVals[$var]);
        }
    }

    /**
     * Checks if the given variable exists. When an array is given, it is checked if all * variables exist
     *
     * @param string|array $var Variable to check
     * @return bool
     */
    function exists($var)
    {
        if (is_array($var)) {
            $isset = true;
            foreach ($var as $varname) {
                $isset = $isset & isset($this->_varVals[$varname]);
            }
            return $isset > 0;
        } else {
            return isset($this->_varVals[$var]);
        }
    }

    /**
     * Sets the template directory
     *
     * @param string $root New template directory
     * @return bool
     * @access public
     */
    function setRoot($root)
    {
        if (!is_dir($root)) {
            return $this->halt('setRoot: ' . $root . ' is not a directory.');
        }

        $this->root = $root;

        return true;
    }

    /**
     * What to do with unknown variables (three possible values- defined by class constants):
     *
     *      VARIABLE_REMOVE:    Will remove unknown variables (don't use this if you define CSS in your page)
     *      VARIABLE_COMMENT:   Will replace undefined variables with comments
     *      VARIABLE_KEEP:      Will keep undefined variables as-is
     *
     * @param string $unknowns Unknowns
     * @return void
     * @access public
     */
    function setUnknowns($unknowns)
    {
        $this->unknowns = $unknowns;
    }

    /**
     * Set appropriate template files
     *
     * With this method you set the template files you want to use. Either you supply an associative array with
     * key/value pairs where the key is the handle for the filname and the value is the filename itself, or you define
     * $handle as the file name handle and $filename as the filename if you want to define only one template.
     *
     * @param mixed $handle Handle for a filename or array with handle/name value pairs
     * @param string $filename Name of template file
     * @return bool True if file could be loaded
     * @access public
     */
    function setFile($handle, $filename = '')
    {
        if (!is_array($handle)) {
            if ($filename == '') {
                return $this->halt('setFile: For handle ' . $handle . ' filename is empty.');
            }

            $file = $this->_filename($filename);

            if ($this->isError($file)) {
                $this->file[$handle] = false;
                return $file;
            }

            $this->file[$handle] = $file;
            return true;
        } else {
            reset($handle);

            $allok = true;

            foreach ($handle as $h => $f) {
                $file = $this->_filename($f);

                if ($this->isError($file)) {
                    $this->file[$h] = false;
                    $allok          = $file;
                } else {
                    $this->file[$h] = $file;
                }
            }

            return $allok;
        }
    }

    /**
     * Set a block in the appropriate template handle
     *
     * By setting a block like that:
     *
     * &lt;!-- BEGIN blockname --&gt;
     * html code
     * &lt;!-- END blockname --&gt;
     *
     * you can easily do repeating HTML code, i.e. output database data nice formatted into a HTML table where each DB
     * row is placed into a HTML table row which is defined in this block. It extracts the template $handle from $parent
     * and places variable {$name} instead.
     *
     * @param string $parent Parent handle
     * @param string $handle Block name handle
     * @param string $name Variable substitution name
     * @return mixed True if all went well
     * @access public
     */
    function setBlock($parent, $handle, $name = '')
    {
        if ($this->isError($this->_loadFile($parent))) {
            return $this->halt(
                'setBlock: unable to load ' . $parent . '.'
            );
        }

        if ($name == '') {
            $name = $handle;
        }

        $str = $this->getVar($parent);
        $reg = "/[ \t]*<!--\\s+BEGIN $handle\\s+-->\\s*?\n?(\\s*.*?\n?)\\s*<!--\\s+END $handle\\s+-->\\s*?\n?/sm";
        $m   = [];
        preg_match_all($reg, $str, $m);
        $str = preg_replace($reg, '{' . $name . '}', $str);

        if (isset($m[1][0])) {
            $this->setVar($handle, $m[1][0]);
        }

        $this->setVar($parent, $str);

        return true;
    }

    /**
     * Set corresponding substitutions for placeholders
     *
     * @param string|array $varname Name of variable to be defined or key/value pair array
     * @param string $value Value of that variable
     * @param boolean $append If true, the value is appended to the variable's existing value
     * @return void
     * @access public
     */
    function setVar($varname, $value = '', $append = false)
    {
        if (!is_array($varname)) {
            if (!empty($varname)) {
                if ($this->debug) {
                    print 'scalar: set *' . $varname . '* to *' . $value . '*<br>\n';
                }
            }

            $this->_varKeys[$varname] = $this->_varname($varname);
            ($append) ? $this->_varVals[$varname] .= $value : $this->_varVals[$varname] = $value;

        } else {
            reset($varname);

            foreach ($varname as $k => $v) {
                if (!empty($k)) {
                    if ($this->debug) {
                        print 'array: set *' . $k . '* to *' . $v . '*<br>\n';
                    }
                }

                $this->_varKeys[$k] = $this->_varname($k);
                ($append) ? $this->_varVals[$k] .= $v : $this->_varVals[$k] = $v;
            }
        }
    }

    /**
     * Substitute variables in handle $handle
     *
     * @param string $handle Name of handle
     * @return mixed String substituted content of handle
     * @access public
     */
    function subst($handle)
    {
        if ($this->isError($this->_loadFile($handle))) {
            return $this->halt('subst: unable to load ' . $handle . '.');
        }

        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        return @str_replace($this->_varKeys, $this->_varVals, $this->getVar($handle));
    }

    /**
     * Same as subst but printing the result
     *
     * @param string $handle Handle of template
     * @return bool always false
     * @access public
     * @see subst
     */
    function pSubst($handle)
    {
        print $this->subst($handle);
        return false;
    }

    /**
     * Parse handle into target
     *
     * Parses handle $handle into $target, eventually appending handle at $target if $append is defined as TRUE
     *
     * @param string $target Target handle to parse into
     * @param string $handle Which handle should be parsed
     * @param boolean $append Append it to $target or not?
     * @return string parsed handle
     * @access public
     */
    function parse($target, $handle, $append = false)
    {
        $str = null;

        if (!is_array($handle)) {
            $str = $this->subst($handle);
            ($append) ? $this->setVar($target, $this->getVar($target) . $str) : $this->setVar($target, $str);
        } else {
            reset($handle);

            foreach ($handle as $k => $h) {
                $str = $this->subst($h);
                $this->setVar($target, $str);
            }
        }

        return $str;
    }

    /**
     * Same as parse, but printing it
     *
     * @param string $target Target to parse into
     * @param string $handle Handle which should be parsed
     * @param bool $append If $handle shall be appended to $target?
     * @return bool
     * @access public
     * @see parse
     */
    function pParse($target, $handle, $append = false)
    {
        print $this->finish($this->parse($target, $handle, $append));
        return false;
    }

    /**
     * Return all defined variables and their values
     *
     * @return array with all defined variables and their values
     * @access public
     */
    function getVars()
    {
        $result = null;
        reset($this->_varKeys);

        foreach ($this->_varKeys as $k => $v) {
            $result[$k] = $this->getVar($k);
        }

        return $result;
    }

    /**
     * Return one or more specific variable(s) with their values.
     *
     * @param mixed $varname Array with variable names or one variable name as a string
     * @return mixed Array of variable names with their values or value of one specific variable
     * @access public
     */
    function getVar($varname)
    {
        if (!is_array($varname)) {
            if (isset($this->_varVals[$varname])) {
                return $this->_varVals[$varname];
            } else {
                return '';
            }
        } else {
            $result = null;
            reset($varname);

            foreach ($varname as $k => $v) {
                $result[$k] = (isset($this->_varVals[$k]))
                    ? $this->_varVals[$k] : '';
            }

            return $result;
        }
    }

    /**
     * Get undefined values of a handle
     *
     * @param string $handle Handle name
     * @return mixed False if an error occurred or the array of undefined values
     * @access public
     */
    function getUndefined($handle)
    {
        if (!$this->_loadFile($handle)) {
            return $this->halt('getUndefined: unable to load ' . $handle);
        }

        preg_match_all("/{([^ \t\r\n}]+)}/", $this->getVar($handle), $m);
        $m = $m[1];

        if (!is_array($m)) {
            return false;
        }

        reset($m);

        foreach ($m as $k => $v) {
            if (!isset($this->_varKeys[$v])) {
                $result[$v] = $v;
            }
        }

        if (isset($result) && count($result)) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Finish string
     *
     * @param string $str String to finish
     * @return string finished, i.e. substituted string
     * @access public
     */
    function finish($str)
    {
        switch ($this->unknowns) {
            case self::VARIABLE_REMOVE:
                $str = preg_replace(
                    '/{[^ \t\r\n}]+}/',
                    '',
                    $str
                );
                break;

            case self::VARIABLE_COMMENT:
                $str = preg_replace(
                    '/{([^ \t\r\n}]+)}/',
                    '<!-- Template variable \\1 undefined -->',
                    $str
                );
                break;
        }

        return $str;
    }

    /**
     * Print variable to the browser
     *
     * @param string $varname Name of variable to print
     * @return void
     * @access public
     */
    function p($varname)
    {
        print $this->finish($this->getVar($varname));
    }

    /**
     * Get finished variable
     *
     * @param string $varname Name of variable to get
     * @return string String with finished variable
     * @access public
     */
    function get($varname)
    {
        return $this->finish($this->getVar($varname));
    }

    /**
     * Complete filename, i.e. testing it for slashes
     *
     * @param string $filename Filename to be completed
     * @access private
     * @return string Completed filename
     */
    function _filename($filename)
    {
        if (!$this->_isAbsolute($filename)) {
            $filename = $this->root . '/' . $filename;
        }

        if (file_exists($filename)) {
            return $filename;
        }

        if (is_array($this->file_fallbacks) && count($this->file_fallbacks) > 0) {
            reset($this->file_fallbacks);

            foreach ($this->file_fallbacks as $k => $v) {
                if (file_exists($v . basename($filename))) {
                    return $v . basename($filename);
                }
            }
            return $this->halt(sprintf(
                'filename: file %s does not exist in the fallback paths %s.',
                $filename,
                implode(',', $this->file_fallbacks)
            ));
        } else {
            return $this->halt(
                sprintf('filename: file %s does not exist.', $filename)
            );
        }
    }


    /**
     * Tells you whether a filename is absolute or relative
     *
     * @param string $filename Filename to check
     * @return boolean true if the filename is absolute
     */
    function _isAbsolute($filename)
    {
        if (substr($filename, 0, 1) == '/') {
            //unix
            return true;
        } elseif (substr($filename, 1, 2) == ':\\') {
            //windows
            return true;
        }

        return false;
    }

    /**
     * Protect a replacement variable
     *
     * @param string $varname name of replacement variable
     * @return string replaced variable
     * @access private
     */
    function _varname($varname)
    {
        return '{' . $varname . '}';
    }

    /**
     * load file defined by handle if it is not loaded yet
     *
     * @param string $handle File handle
     * @return bool False if error, true if all is ok
     * @access private
     */
    function _loadFile($handle)
    {
        if (isset($this->_varKeys[$handle]) and !empty($this->_varVals[$handle])) {
            return true;
        }

        if (!isset($this->file[$handle])) {
            return $this->halt('loadfile: ' . $handle . ' is not a valid handle.');
        }

        $filename = $this->file[$handle];

        if (function_exists('file_get_contents')) {
            $str = file_get_contents($filename);
        } else {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            if (!$fp = @fopen($filename, 'r')) {
                return $this->halt('loadfile: couldn\'t open ' . $filename);
            }

            $str = fread($fp, filesize($filename));
            fclose($fp);
        }

        if ($str == '') {
            return $this->halt("loadfile: While loading {$handle}, {$filename} does not exist or is empty.");
        }

        $this->setVar($handle, $str);

        return true;
    }

    /**
     * Error function. Halt template system with message to show
     *
     * @param string $msg message to show
     * @return bool
     * @access public
     */
    function halt($msg)
    {
        $this->_lastError = $msg;

        switch ($this->haltOnError) {
            case self::HALT_YES:
                trigger_error($msg, E_USER_ERROR);
                return false;
            case self::HALT_RETURN:
            case self::HALT_REPORT:
                trigger_error($msg, E_USER_WARNING);
                return false;
            case self::HALT_NO:
            default:
                return false;
        }
    }

    /**
     * Returns the last error message if any
     *
     * @return boolean|string Last error message if any, false if none.
     */
    function getLastError()
    {
        if ($this->_lastError == '') {
            return false;
        }
        return $this->_lastError;
    }

    /**
     * Checks if the given value is an error
     *
     * @param mixed $val
     * @return bool
     */
    function isError($val)
    {
        return $val === false;
    }

    // Deprecated Functions

    /**
     * @deprecated
     * @param $handle
     * @param string $filename
     * @return bool
     */
    function set_file($handle, $filename = '')
    {
        return $this->setFile($handle, $filename);
    }

    /**
     * @deprecated
     * @param $parent
     * @param $handle
     * @param string $name
     * @return mixed
     */
    function set_block($parent, $handle, $name = '')
    {
        return $this->setBlock($parent, $handle, $name);
    }

    /**
     * @deprecated
     * @param string $varname
     * @param string $value
     * @param bool $append
     */
    function set_var($varname, $value = '', $append = false)
    {
        $this->setVar($varname, $value, $append);
    }

    /**
     * @deprecated
     * @param $varname
     * @return mixed
     */
    function get_var($varname)
    {
        return $this->getVar($varname);
    }
}
