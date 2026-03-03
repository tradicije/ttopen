<?php
/**
 * OpenTT - Table Tennis Management Plugin
 * Copyright (C) 2026 Aleksa Dimitrijević
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 */

namespace OpenTT\Unified\Infrastructure;

final class AdminUiTranslator
{
    public static function availableLanguages($pluginDir)
    {
        static $cache = [];

        $pluginDir = (string) $pluginDir;
        if ($pluginDir === '') {
            return ['sr' => 'Srpski', 'en' => 'English'];
        }
        if (isset($cache[$pluginDir]) && is_array($cache[$pluginDir])) {
            return $cache[$pluginDir];
        }

        $langs = ['sr' => 'sr', 'en' => 'en'];
        $globPattern = trailingslashit($pluginDir) . 'languages/admin-ui-*.txt';
        $files = glob($globPattern);
        if (is_array($files)) {
            foreach ($files as $file) {
                $base = wp_basename((string) $file);
                if ($base === 'admin-ui-all-strings.txt' || $base === 'admin-ui-source-sr-to-en.txt') {
                    continue;
                }
                if (preg_match('/^admin-ui-([a-z0-9_-]+)\.txt$/i', $base, $m)) {
                    $code = sanitize_key((string) $m[1]);
                    if ($code !== '') {
                        $langs[$code] = $code;
                    }
                }
            }
        }

        $labels = self::availableLanguageLabels();
        $out = [];
        foreach ($langs as $code) {
            $out[$code] = isset($labels[$code]) ? $labels[$code] : strtoupper((string) $code);
        }

        $cache[$pluginDir] = $out;
        return $out;
    }

    public static function translateHtml($html, $pluginDir, $lang)
    {
        $html = (string) $html;
        if ($html === '') {
            return $html;
        }

        $lang = sanitize_key((string) $lang);
        if ($lang === '' || $lang === 'sr') {
            return $html;
        }

        $pluginDir = (string) $pluginDir;
        if ($pluginDir === '') {
            return $html;
        }

        static $bridgeMapByDir = [];
        if (!isset($bridgeMapByDir[$pluginDir]) || !is_array($bridgeMapByDir[$pluginDir])) {
            $bridgePath = trailingslashit($pluginDir) . 'languages/admin-ui-source-sr-to-en.txt';
            $bridgeMapByDir[$pluginDir] = self::parseTxtMap($bridgePath);
        }

        $bridgeMap = $bridgeMapByDir[$pluginDir];
        if (!empty($bridgeMap)) {
            $safeBridge = [];
            foreach ($bridgeMap as $key => $val) {
                if (self::isSafeKey((string) $key)) {
                    $safeBridge[(string) $key] = (string) $val;
                }
            }
            if (!empty($safeBridge)) {
                $html = strtr($html, $safeBridge);
            }
        }

        if ($lang === 'en') {
            return $html;
        }

        static $langMapCache = [];
        if (!isset($langMapCache[$pluginDir])) {
            $langMapCache[$pluginDir] = [];
        }
        if (!isset($langMapCache[$pluginDir][$lang]) || !is_array($langMapCache[$pluginDir][$lang])) {
            $langPath = trailingslashit($pluginDir) . 'languages/admin-ui-' . $lang . '.txt';
            $langMapCache[$pluginDir][$lang] = self::parseTxtMap($langPath);
        }

        $map = $langMapCache[$pluginDir][$lang];
        if (empty($map)) {
            return $html;
        }

        $safeMap = [];
        foreach ($map as $key => $val) {
            if (self::isSafeKey((string) $key)) {
                $safeMap[(string) $key] = (string) $val;
            }
        }

        if (empty($safeMap)) {
            return $html;
        }

        return strtr($html, $safeMap);
    }

    private static function availableLanguageLabels()
    {
        return [
            'sr' => 'Srpski',
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ru' => 'Русский',
            'hu' => 'Magyar',
            'ro' => 'Română',
        ];
    }

    private static function parseTxtMap($path)
    {
        if (!is_string($path) || $path === '' || !is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $map = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $sepPos = strpos($line, ' = ');
            if ($sepPos !== false) {
                $key = trim((string) substr($line, 0, $sepPos));
                $val = trim((string) substr($line, $sepPos + 3));
            } else {
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $key = trim((string) $parts[0]);
                $val = trim((string) $parts[1]);
            }
            if ($key === '') {
                continue;
            }

            $key = str_replace(['\\n', '\\='], ["\n", '='], $key);
            $val = str_replace(['\\n', '\\='], ["\n", '='], $val);
            $map[$key] = $val;
        }

        return $map;
    }

    private static function isSafeKey($key)
    {
        $key = (string) $key;
        if ($key === '') {
            return false;
        }

        if (strpos($key, '<') !== false || strpos($key, '>') !== false) {
            return true;
        }

        if (preg_match('/[\\s:\\.\\,\\?\\!\\(\\)\\[\\]\\/]/u', $key)) {
            return true;
        }

        return mb_strlen($key, 'UTF-8') >= 16;
    }
}
