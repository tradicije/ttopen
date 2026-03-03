<?php

namespace OpenTT\Unified;

final class Plugin
{
    public static function boot($pluginFile)
    {
        self::registerLocalAutoloader();
        self::loadLegacyCore();

        register_activation_hook($pluginFile, function () use ($pluginFile) {
            \OpenTT_Unified_Core::activate($pluginFile);
        });

        \OpenTT_Unified_Core::init($pluginFile);
    }

    private static function registerLocalAutoloader()
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        spl_autoload_register(function ($class) {
            $prefix = 'OpenTT\\Unified\\';
            if (strpos((string) $class, $prefix) !== 0) {
                return;
            }

            $relative = substr((string) $class, strlen($prefix));
            if (!is_string($relative) || $relative === '') {
                return;
            }

            $relativePath = str_replace('\\', '/', $relative) . '.php';
            $file = dirname(__DIR__) . '/src/' . $relativePath;
            if (is_readable($file)) {
                require_once $file;
            }
        });

        $registered = true;
    }

    private static function loadLegacyCore()
    {
        if (\class_exists('\OpenTT_Unified_Core')) {
            return;
        }

        require_once dirname(__DIR__) . '/includes/class-opentt-unified-core.php';
    }
}
