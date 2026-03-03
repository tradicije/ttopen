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


if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Admin_Club_Player_Actions
{
    public static function handle_save_club_admin()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_save_club');
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $title = sanitize_text_field((string) ($_POST['post_title'] ?? ''));
        if ($title === '') {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-club'), 'error', 'Naziv kluba je obavezan.'));
            exit;
        }

        $post_data = [
            'post_type' => 'klub',
            'post_title' => $title,
            'post_content' => wp_kses_post((string) ($_POST['post_content'] ?? '')),
            'post_status' => 'publish',
        ];
        if ($id > 0) {
            $post_data['ID'] = $id;
            wp_update_post($post_data);
            $club_id = $id;
        } else {
            $club_id = wp_insert_post($post_data);
        }
        if (!$club_id || is_wp_error($club_id)) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-club'), 'error', 'Greška pri čuvanju kluba.'));
            exit;
        }

        $opstina_in = sanitize_text_field((string) ($_POST['opstina'] ?? ''));
        $opstina_opts = OpenTT_Unified_Admin_Readonly_Helpers::municipality_options_admin();
        if ($opstina_in !== '' && !in_array($opstina_in, $opstina_opts, true)) {
            $opstina_in = '';
        }
        update_post_meta($club_id, 'opstina', $opstina_in);
        update_post_meta($club_id, 'grad', sanitize_text_field((string) ($_POST['grad'] ?? '')));
        update_post_meta($club_id, 'kontakt', sanitize_text_field((string) ($_POST['kontakt'] ?? '')));
        update_post_meta($club_id, 'email', sanitize_email((string) ($_POST['email'] ?? '')));
        update_post_meta($club_id, 'zastupnik_kluba', sanitize_text_field((string) ($_POST['zastupnik_kluba'] ?? '')));
        $website = trim((string) ($_POST['website_kluba'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }
        update_post_meta($club_id, 'website_kluba', esc_url_raw($website));
        update_post_meta($club_id, 'boja_dresa', sanitize_text_field((string) ($_POST['boja_dresa'] ?? '')));
        update_post_meta($club_id, 'loptice', sanitize_text_field((string) ($_POST['loptice'] ?? '')));
        update_post_meta($club_id, 'adresa_kluba', sanitize_text_field((string) ($_POST['adresa_kluba'] ?? '')));
        update_post_meta($club_id, 'adresa_sale', sanitize_text_field((string) ($_POST['adresa_sale'] ?? '')));
        update_post_meta($club_id, 'termin_igranja', sanitize_text_field((string) ($_POST['termin_igranja'] ?? '')));

        $thumb_id = isset($_POST['featured_image_id']) ? (int) $_POST['featured_image_id'] : 0;
        if ($thumb_id > 0) {
            set_post_thumbnail($club_id, $thumb_id);
        } else {
            delete_post_thumbnail($club_id);
        }

        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-clubs'), 'success', 'Klub je sačuvan.'));
        exit;
    }

    public static function handle_delete_club_admin()
    {
        self::require_cap();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            wp_die('Nedostaje ID.');
        }
        check_admin_referer('opentt_unified_delete_club_' . $id);
        wp_trash_post($id);
        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-clubs'), 'success', 'Klub je obrisan.'));
        exit;
    }

    public static function handle_delete_clubs_bulk_admin()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_delete_clubs_bulk');

        $ids = isset($_POST['club_ids']) && is_array($_POST['club_ids']) ? array_map('intval', (array) $_POST['club_ids']) : [];
        $ids = array_values(array_unique(array_filter($ids, static function ($v) {
            return $v > 0;
        })));

        $base_url = admin_url('admin.php?page=stkb-unified-clubs');
        $search = isset($_POST['club_search']) ? sanitize_text_field((string) wp_unslash($_POST['club_search'])) : '';
        if ($search !== '') {
            $base_url = add_query_arg('club_search', $search, $base_url);
        }

        if (empty($ids)) {
            wp_safe_redirect(self::admin_notice_url($base_url, 'error', 'Nije izabran nijedan klub.'));
            exit;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== 'klub') {
                continue;
            }
            wp_trash_post($id);
            $deleted++;
        }

        if ($deleted <= 0) {
            wp_safe_redirect(self::admin_notice_url($base_url, 'error', 'Nijedan izabrani klub nije obrisan.'));
            exit;
        }

        wp_safe_redirect(self::admin_notice_url($base_url, 'success', 'Obrisano klubova: ' . $deleted . '.'));
        exit;
    }

    public static function handle_save_player_admin()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_save_player');
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $title = sanitize_text_field((string) ($_POST['post_title'] ?? ''));
        if ($title === '') {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-player'), 'error', 'Ime i prezime su obavezni.'));
            exit;
        }

        $post_data = [
            'post_type' => 'igrac',
            'post_title' => $title,
            'post_content' => wp_kses_post((string) ($_POST['post_content'] ?? '')),
            'post_status' => 'publish',
        ];
        if ($id > 0) {
            $post_data['ID'] = $id;
            wp_update_post($post_data);
            $player_id = $id;
        } else {
            $player_id = wp_insert_post($post_data);
        }
        if (!$player_id || is_wp_error($player_id)) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-player'), 'error', 'Greška pri čuvanju igrača.'));
            exit;
        }

        $club_id = (int) ($_POST['povezani_klub'] ?? 0);
        update_post_meta($player_id, 'povezani_klub', $club_id);
        update_post_meta($player_id, 'klub_igraca', $club_id);
        update_post_meta($player_id, 'datum_rodjenja', sanitize_text_field((string) ($_POST['datum_rodjenja'] ?? '')));
        update_post_meta($player_id, 'mesto_rodjenja', sanitize_text_field((string) ($_POST['mesto_rodjenja'] ?? '')));
        $country_code = strtoupper(sanitize_key((string) ($_POST['drzavljanstvo'] ?? 'RS')));
        if (!array_key_exists($country_code, OpenTT_Unified_Admin_Readonly_Helpers::country_options_admin())) {
            $country_code = 'RS';
        }
        update_post_meta($player_id, 'drzavljanstvo', $country_code);

        $thumb_id = isset($_POST['featured_image_id']) ? (int) $_POST['featured_image_id'] : 0;
        if ($thumb_id > 0) {
            set_post_thumbnail($player_id, $thumb_id);
        } else {
            delete_post_thumbnail($player_id);
        }

        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-players'), 'success', 'Igrač je sačuvan.'));
        exit;
    }

    public static function handle_delete_player_admin()
    {
        self::require_cap();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            wp_die('Nedostaje ID.');
        }
        check_admin_referer('opentt_unified_delete_player_' . $id);
        wp_trash_post($id);
        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-players'), 'success', 'Igrač je obrisan.'));
        exit;
    }

    public static function handle_delete_players_bulk_admin()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_delete_players_bulk');

        $ids = isset($_POST['player_ids']) && is_array($_POST['player_ids']) ? array_map('intval', (array) $_POST['player_ids']) : [];
        $ids = array_values(array_unique(array_filter($ids, static function ($v) {
            return $v > 0;
        })));

        $base_url = admin_url('admin.php?page=stkb-unified-players');
        $search = isset($_POST['player_search']) ? sanitize_text_field((string) wp_unslash($_POST['player_search'])) : '';
        $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
        $liga_slug = isset($_POST['liga_slug']) ? sanitize_title((string) wp_unslash($_POST['liga_slug'])) : '';
        $sort_by = isset($_POST['sort_by']) ? sanitize_key((string) wp_unslash($_POST['sort_by'])) : '';
        $sort_dir = isset($_POST['sort_dir']) ? strtoupper(sanitize_key((string) wp_unslash($_POST['sort_dir']))) : '';
        if ($search !== '') {
            $base_url = add_query_arg('player_search', $search, $base_url);
        }
        if ($club_id > 0) {
            $base_url = add_query_arg('club_id', $club_id, $base_url);
        }
        if ($liga_slug !== '') {
            $base_url = add_query_arg('liga_slug', $liga_slug, $base_url);
        }
        if (in_array($sort_by, ['player', 'club', 'league'], true) && $sort_by !== 'player') {
            $base_url = add_query_arg('sort_by', $sort_by, $base_url);
        }
        if (in_array($sort_dir, ['ASC', 'DESC'], true) && $sort_dir !== 'ASC') {
            $base_url = add_query_arg('sort_dir', $sort_dir, $base_url);
        }

        if (empty($ids)) {
            wp_safe_redirect(self::admin_notice_url($base_url, 'error', 'Nije izabran nijedan igrač.'));
            exit;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== 'igrac') {
                continue;
            }
            wp_trash_post($id);
            $deleted++;
        }

        if ($deleted <= 0) {
            wp_safe_redirect(self::admin_notice_url($base_url, 'error', 'Nijedan izabrani igrač nije obrisan.'));
            exit;
        }

        wp_safe_redirect(self::admin_notice_url($base_url, 'success', 'Obrisano igrača: ' . $deleted . '.'));
        exit;
    }

    private static function require_cap()
    {
        if (!current_user_can(OpenTT_Unified_Core::CAP)) {
            wp_die('Nemaš dozvolu za ovu akciju.');
        }
    }

    private static function admin_notice_url($url, $type, $message)
    {
        return add_query_arg([
            'opentt_notice' => sanitize_key((string) $type),
            'opentt_msg' => (string) $message,
        ], $url);
    }
}
