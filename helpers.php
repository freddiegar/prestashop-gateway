<?php

if (!function_exists('getPathCMS')) {
    /**
     * @param string $filename
     * @return mixed|string
     */
    function getPathCMS($filename)
    {
        $option = 'Default';
        $pathUsed = getcwd();
        $pathCMS = dirname(dirname($pathUsed));

        if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
            $option = 'PWD';
            $pathUsed = $_SERVER['PWD'];
            $pathCMS = dirname(dirname($pathUsed));
        } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
            $option = 'File';
            // Case:
            // IIS:     \ (backslash)
            // Apache:  / (slash)
            $pathUsed = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_FILENAME']);
            $pathCMS = str_replace(
                DIRECTORY_SEPARATOR . 'modules' .
                DIRECTORY_SEPARATOR . getModuleName() .
                DIRECTORY_SEPARATOR . $filename,
                '',
                $pathUsed
            );
        }

        if (!file_exists($pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php')) {
            $message = "Miss-configuration in Server [mode: " . php_sapi_name() . "] [{$filename}]" . breakLine();
            $message .= "Option [{$option}]" . breakLine();
            $message .= "Used [{$pathUsed}]" . breakLine();
            $message .= "Path [{$pathCMS}]" . breakLine();

            die($message);
        }

        return $pathCMS;
    }
}

if (!function_exists('versionComparePlaceToPay')) {
    /**
     * @param string $version
     * @param string $operator
     * @return bool
     */
    function versionComparePlaceToPay($version, $operator)
    {
        return version_compare(_PS_VERSION_, $version, $operator);
    }
}

if (!function_exists('isDebugEnable')) {
    /**
     * @return bool
     */
    function isDebugEnable()
    {
        return defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ === true;
    }
}

if (!function_exists('isConsole')) {
    /**
     * @return bool
     */
    function isConsole()
    {
        static $isConsole;

        if (is_null($isConsole)) {
            $isConsole = 'cli' == php_sapi_name();
        }

        return $isConsole;
    }
}

if (!function_exists('breakLine')) {
    /**
     * @param int $multiplier
     * @return string
     */
    function breakLine($multiplier = 1)
    {
        static $breakLine;

        if (is_null($breakLine)) {
            $breakLine = isConsole() ? PHP_EOL : '<br />';
        }

        return str_repeat($breakLine, $multiplier);
    }
}

if (!function_exists('getModuleName')) {
    /**
     * @return string
     */
    function getModuleName()
    {
        return 'placetopaypayment';
    }
}

if (!function_exists('fixPath')) {
    /**
     * @param string $path
     * @return string
     */
    function fixPath($path)
    {
        return str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
    }
}
