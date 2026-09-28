<?php

namespace Kanboard\Plugin\StatusReport\Core;

/**
 * Detects collisions between this plugin and any other plugin that overrides
 * the same core templates.
 *
 * Detection is based on the effective template mapping (behaviour), never on a
 * hardcoded list of incompatible plugin names, and happens in two steps:
 *
 *  1) before registering our own override (catches plugins loaded before us);
 *  2) on app.bootstrap, when every plugin has been initialized (catches
 *     plugins loaded after us that replaced our mapping).
 *
 * When a conflict is found the feature is not activated at all: no template is
 * replaced, no partial state is left behind, and administrators get an explicit
 * message.
 */
class OverrideGuard
{
    private static $conflicts = array();
    private static $pluginDir = '';

    public static function reset($pluginDir = '')
    {
        self::$conflicts = array();

        if ($pluginDir !== '') {
            self::$pluginDir = self::normalize($pluginDir);
        }
    }

    public static function addConflict($template, $file)
    {
        self::$conflicts[$template] = array(
            'template' => $template,
            'file'     => $file,
            'plugin'   => self::getPluginNameFromFile($file),
        );
    }

    public static function hasConflict($template)
    {
        return isset(self::$conflicts[$template]);
    }

    public static function hasConflicts()
    {
        return ! empty(self::$conflicts);
    }

    public static function getConflicts()
    {
        return self::$conflicts;
    }

    /**
     * A template is a core template when it does not live in the plugins directory.
     */
    public static function isCoreTemplate($file)
    {
        $pluginsDir = self::normalize(PLUGINS_DIR);

        return strpos(self::normalize($file), $pluginsDir.DIRECTORY_SEPARATOR) !== 0;
    }

    public static function isOwnedByPlugin($file)
    {
        return strpos(self::normalize($file), self::$pluginDir.DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * /var/www/plugins/Foo/Template/comment/show.php => Foo
     */
    public static function getPluginNameFromFile($file)
    {
        $file = self::normalize($file);
        $pluginsDir = self::normalize(PLUGINS_DIR).DIRECTORY_SEPARATOR;

        if (strpos($file, $pluginsDir) !== 0) {
            return '?';
        }

        $parts = explode(DIRECTORY_SEPARATOR, substr($file, strlen($pluginsDir)));

        return $parts[0];
    }

    /**
     * Resolve "." and ".." without requiring the file to exist.
     */
    private static function normalize($path)
    {
        $real = realpath($path);

        if ($real !== false) {
            return $real;
        }

        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $absolute = $path !== '' && $path[0] === DIRECTORY_SEPARATOR;
        $segments = array();

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }

        return ($absolute ? DIRECTORY_SEPARATOR : '').implode(DIRECTORY_SEPARATOR, $segments);
    }
}
