<?php

if (!defined('ABSPATH')) {
    exit;
}

final class STKB_Unified_Readonly_Helpers
{
    public static function parse_date_to_sql($raw)
    {
        if (!$raw) {
            return null;
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }

    public static function extract_id($raw)
    {
        if (is_numeric($raw)) {
            return intval($raw);
        }

        if (is_object($raw) && isset($raw->ID)) {
            return intval($raw->ID);
        }

        if (is_array($raw)) {
            if (isset($raw['ID']) && is_numeric($raw['ID'])) {
                return intval($raw['ID']);
            }

            if (isset($raw[0])) {
                return self::extract_id($raw[0]);
            }
        }

        return 0;
    }

    public static function extract_ids($raw)
    {
        $raw = maybe_unserialize($raw);

        if (is_numeric($raw) || is_object($raw)) {
            $id = self::extract_id($raw);
            return $id ? [$id] : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            $id = self::extract_id($item);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    public static function parse_legacy_liga_sezona($liga_slug, $sezona_slug)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);

        if ($liga_slug === '') {
            return [
                'league_slug' => '',
                'season_slug' => $sezona_slug,
            ];
        }

        if ($sezona_slug !== '') {
            return [
                'league_slug' => $liga_slug,
                'season_slug' => $sezona_slug,
            ];
        }

        $m = [];
        if (preg_match('/^(.*?)-((?:19|20)\d{2})-(\d{2}|\d{4})$/', $liga_slug, $m)) {
            $base = sanitize_title((string) $m[1]);
            $year_a = (string) $m[2];
            $year_b = (string) $m[3];
            if ($base !== '') {
                return [
                    'league_slug' => $base,
                    'season_slug' => sanitize_title($year_a . '-' . $year_b),
                ];
            }
        }

        return [
            'league_slug' => $liga_slug,
            'season_slug' => '',
        ];
    }

    public static function slug_to_title($slug)
    {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return '';
        }
        $title = str_replace('-', ' ', $slug);
        return ucwords($title);
    }

    public static function normalize_phone_for_href($raw_phone)
    {
        $raw = trim((string) $raw_phone);
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/\s+/', '', $raw);
        if (strpos($raw, '00') === 0) {
            $raw = '+' . substr($raw, 2);
        }

        $clean = preg_replace('/[^0-9+]/', '', $raw);
        if ($clean === '') {
            return '';
        }

        if (strpos($clean, '+') !== false) {
            $clean = '+' . str_replace('+', '', $clean);
        }

        return $clean;
    }

    public static function format_phone_for_display($raw_phone)
    {
        $raw = trim((string) $raw_phone);
        if ($raw === '') {
            return '';
        }

        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return $raw;
        }

        if (strpos($digits, '381') === 0) {
            $digits = '0' . substr($digits, 3);
        }

        if (strpos($digits, '0') !== 0 && strlen($digits) === 9) {
            $digits = '0' . $digits;
        }

        if (preg_match('/^0\d{9}$/', $digits)) {
            $prefix = substr($digits, 0, 3);
            $rest = substr($digits, 3);
            return $prefix . '/' . substr($rest, 0, 3) . '-' . substr($rest, 3, 2) . '-' . substr($rest, 5, 2);
        }

        return $raw;
    }

    public static function season_sort_key($season_slug)
    {
        $season_slug = sanitize_title((string) $season_slug);
        if (preg_match('/^(\d{4})-(\d{2,4})$/', $season_slug, $m)) {
            return intval($m[1]);
        }
        if (preg_match('/(\d{4})/', $season_slug, $m)) {
            return intval($m[1]);
        }
        return 0;
    }

    public static function display_match_date($match_date)
    {
        $match_date = (string) $match_date;
        if ($match_date === '' || $match_date === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($match_date);
        if ($ts === false) {
            return '';
        }
        return date_i18n('d.m.Y.', $ts);
    }

    public static function display_match_date_long($match_date)
    {
        $match_date = (string) $match_date;
        if ($match_date === '' || $match_date === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($match_date);
        if ($ts === false) {
            return '';
        }
        return date_i18n('d. F Y.', $ts);
    }

    public static function extract_round_no($kolo_slug)
    {
        $kolo_slug = (string) $kolo_slug;
        if ($kolo_slug === '') {
            return 0;
        }
        if (preg_match('/^([0-9]+)/', $kolo_slug, $m)) {
            return intval($m[1]);
        }
        if (preg_match('/([0-9]+)/', $kolo_slug, $m)) {
            return intval($m[1]);
        }
        return 0;
    }
}
