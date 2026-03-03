<?php

if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Admin_Readonly_Helpers
{
    public static function leagues_dropdown_admin($name, $selected_slug, $required = true)
    {
        $rows = get_posts([
            'post_type' => 'liga',
            'numberposts' => 400,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        $selected_slug = sanitize_title((string) $selected_slug);
        $attr = $required ? ' required' : '';
        $html = '<select name="' . esc_attr((string) $name) . '" id="stkb_league_select"' . $attr . '>';
        $html .= '<option value="">— izaberi —</option>';
        $seen = [];
        foreach ($rows as $r) {
            $slug = (string) $r->post_name;
            $seen[$slug] = true;
            $html .= '<option value="' . esc_attr($slug) . '" ' . selected($selected_slug, $slug, false) . '>' . esc_html((string) $r->post_title) . '</option>';
        }
        if ($selected_slug !== '' && empty($seen[$selected_slug])) {
            $html .= '<option value="' . esc_attr($selected_slug) . '" selected>' . esc_html($selected_slug . ' (legacy)') . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function competition_rules_dropdown_admin($name, $selected_id, $required = true)
    {
        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 1000,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        $selected_id = (int) $selected_id;
        $attr = $required ? ' required' : '';
        $html = '<select name="' . esc_attr((string) $name) . '"' . $attr . '>';
        $html .= '<option value="">— izaberi —</option>';
        foreach ($rows as $r) {
            $liga_slug = (string) get_post_meta($r->ID, 'opentt_competition_league_slug', true);
            $sezona_slug = (string) get_post_meta($r->ID, 'opentt_competition_season_slug', true);
            $label = OpenTT_Unified_Readonly_Helpers::slug_to_title($liga_slug) . ' / ' . OpenTT_Unified_Readonly_Helpers::slug_to_title($sezona_slug);
            if (trim($label) === '/') {
                $label = (string) $r->post_title;
            }
            $html .= '<option value="' . (int) $r->ID . '" ' . selected($selected_id, (int) $r->ID, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function seasons_dropdown_admin($name, $selected_slug, $required = false)
    {
        $rows = get_posts([
            'post_type' => 'sezona',
            'numberposts' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        $selected_slug = sanitize_title((string) $selected_slug);
        $attr = $required ? ' required' : '';
        $html = '<select name="' . esc_attr((string) $name) . '" id="stkb_season_select"' . $attr . '>';
        $html .= '<option value="">— bez sezone —</option>';
        $seen = [];
        foreach ($rows as $r) {
            $slug = (string) $r->post_name;
            $seen[$slug] = true;
            $html .= '<option value="' . esc_attr($slug) . '" ' . selected($selected_slug, $slug, false) . '>' . esc_html((string) $r->post_title) . '</option>';
        }
        if ($selected_slug !== '' && empty($seen[$selected_slug])) {
            $html .= '<option value="' . esc_attr($selected_slug) . '" selected>' . esc_html($selected_slug . ' (legacy)') . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function clubs_dropdown_admin($name, $selected, $required)
    {
        $rows = get_posts([
            'post_type' => 'klub',
            'numberposts' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        $attr = $required ? ' required' : '';
        $html = '<select name="' . esc_attr((string) $name) . '"' . $attr . '>';
        $html .= '<option value="">— izaberi —</option>';
        foreach ($rows as $r) {
            $html .= '<option value="' . (int) $r->ID . '" ' . selected((int) $selected, (int) $r->ID, false) . '>' . esc_html((string) $r->post_title) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function municipality_dropdown_admin($name, $selected, $required)
    {
        $selected = sanitize_text_field((string) $selected);
        $options = self::municipality_options_admin();
        $attr = $required ? ' required' : '';
        $html = '<select name="' . esc_attr((string) $name) . '"' . $attr . '>';
        $html .= '<option value="">— izaberi opštinu —</option>';
        foreach ($options as $label) {
            $label = (string) $label;
            $html .= '<option value="' . esc_attr($label) . '" ' . selected($selected, $label, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function country_dropdown_admin($name, $selected, $required)
    {
        $selected = strtoupper(sanitize_key((string) $selected));
        $options = self::country_options_admin();
        $attr = $required ? ' required' : '';
        $html = '<select name="' . esc_attr((string) $name) . '"' . $attr . '>';
        $html .= '<option value="">— izaberi državu —</option>';
        foreach ($options as $code => $label) {
            $html .= '<option value="' . esc_attr((string) $code) . '" ' . selected($selected, (string) $code, false) . '>' . esc_html((string) $label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function municipality_options_admin()
    {
        return [
            'Ada', 'Aleksandrovac', 'Aleksinac', 'Alibunar', 'Apatin', 'Aranđelovac', 'Arilje',
            'Babušnica', 'Bajina Bašta', 'Barajevo', 'Batočina', 'Bač', 'Bačka Palanka', 'Bačka Topola', 'Bački Petrovac',
            'Bela Crkva', 'Bela Palanka', 'Bečej', 'Blace', 'Bogatić', 'Bojnik', 'Boljevac', 'Bor',
            'Bosilegrad', 'Brus', 'Bujanovac', 'Valjevo', 'Varvarin', 'Velika Plana', 'Veliko Gradište',
            'Vladimirci', 'Vladičin Han', 'Vlasotince', 'Vračar', 'Vrbas', 'Vrnjačka Banja', 'Vršac', 'Voždovac',
            'Gadžin Han', 'Golubac', 'Gornji Milanovac', 'Grocka', 'Despotovac', 'Dimitrovgrad', 'Doljevac',
            'Žabalj', 'Žabari', 'Žagubica', 'Žitište', 'Zaječar', 'Zvezdara', 'Zemun', 'Zrenjanin',
            'Ivanjica', 'Inđija', 'Irig',
            'Jagodina', 'Kanjiža', 'Kladovo', 'Knić', 'Knjaževac', 'Kovačica', 'Kovin', 'Kosjerić', 'Koceljeva',
            'Kragujevac', 'Kraljevo', 'Krupanj', 'Kruševac', 'Kučevo', 'Kula', 'Kuršumlija',
            'Lajkovac', 'Lapovo', 'Lebane', 'Leposavić', 'Leskovac', 'Loznica', 'Lučani', 'Ljig',
            'Mali Zvornik', 'Mali Iđoš', 'Malo Crniće', 'Medveđa', 'Merošina', 'Mionica', 'Negotin',
            'Niš', 'Niška Banja', 'Novi Bečej', 'Novi Kneževac', 'Novi Pazar', 'Novi Sad', 'Nova Crnja',
            'Nova Varoš', 'Obrenovac', 'Odžaci', 'Opovo', 'Orahovac', 'Osečina',
            'Palilula', 'Palilula (Niš)', 'Pančevo', 'Paraćin', 'Petrovac na Mlavi', 'Pećinci', 'Pirot', 'Plandište',
            'Požarevac', 'Požega', 'Preševo', 'Priboj', 'Prijepolje', 'Prokuplje',
            'Rača', 'Raška', 'Rakovica', 'Ražanj', 'Rekovac', 'Ruma',
            'Savski Venac', 'Svilajnac', 'Svrljig', 'Senta', 'Sečanj', 'Sjenica', 'Smederevo',
            'Smederevska Palanka', 'Sokobanja', 'Sombor', 'Sopot', 'Srbobran', 'Sremska Mitrovica', 'Sremski Karlovci',
            'Stara Pazova', 'Stari Grad', 'Subotica', 'Surdulica', 'Surčin',
            'Temerin', 'Titel', 'Topola', 'Trgovište', 'Trstenik', 'Tutin',
            'Ub', 'Užice',
            'Čajetina', 'Čačak', 'Čoka', 'Čukarica',
            'Šabac', 'Šid',
        ];
    }

    public static function country_label_by_code($code)
    {
        $code = strtoupper(sanitize_key((string) $code));
        if ($code === '') {
            return '';
        }
        $options = self::country_options_admin();
        return isset($options[$code]) ? (string) $options[$code] : '';
    }

    public static function country_flag_emoji($code)
    {
        $code = strtoupper(preg_replace('/[^A-Z]/', '', (string) $code));
        if (strlen($code) !== 2) {
            return '';
        }
        $base = 127397;
        $chars = preg_split('//u', $code, -1, PREG_SPLIT_NO_EMPTY);
        if (count($chars) !== 2) {
            return '';
        }
        $flag = '';
        foreach ($chars as $ch) {
            $cp = ord($ch);
            if ($cp < 65 || $cp > 90) {
                return '';
            }
            $flag .= self::codepoint_to_utf8($base + $cp);
        }
        return $flag;
    }

    public static function country_options_admin()
    {
        return [
            'AL' => 'Albanija',
            'AR' => 'Argentina',
            'AT' => 'Austrija',
            'AU' => 'Australija',
            'BA' => 'Bosna i Hercegovina',
            'BE' => 'Belgija',
            'BG' => 'Bugarska',
            'BR' => 'Brazil',
            'BY' => 'Belorusija',
            'CA' => 'Kanada',
            'CH' => 'Švajcarska',
            'CL' => 'Čile',
            'CN' => 'Kina',
            'CO' => 'Kolumbija',
            'CR' => 'Kostarika',
            'CU' => 'Kuba',
            'CY' => 'Kipar',
            'CZ' => 'Češka',
            'DE' => 'Nemačka',
            'DK' => 'Danska',
            'DO' => 'Dominikanska Republika',
            'DZ' => 'Alžir',
            'EE' => 'Estonija',
            'EG' => 'Egipat',
            'ES' => 'Španija',
            'FI' => 'Finska',
            'FR' => 'Francuska',
            'GB' => 'Ujedinjeno Kraljevstvo',
            'GE' => 'Gruzija',
            'GR' => 'Grčka',
            'HR' => 'Hrvatska',
            'HU' => 'Mađarska',
            'IE' => 'Irska',
            'IL' => 'Izrael',
            'IN' => 'Indija',
            'IR' => 'Iran',
            'IS' => 'Island',
            'IT' => 'Italija',
            'JP' => 'Japan',
            'KR' => 'Južna Koreja',
            'KZ' => 'Kazahstan',
            'LT' => 'Litvanija',
            'LU' => 'Luksemburg',
            'LV' => 'Letonija',
            'MA' => 'Maroko',
            'MD' => 'Moldavija',
            'ME' => 'Crna Gora',
            'MK' => 'Severna Makedonija',
            'MT' => 'Malta',
            'MX' => 'Meksiko',
            'NL' => 'Holandija',
            'NO' => 'Norveška',
            'NZ' => 'Novi Zeland',
            'PE' => 'Peru',
            'PL' => 'Poljska',
            'PT' => 'Portugal',
            'RO' => 'Rumunija',
            'RS' => 'Srbija',
            'RU' => 'Rusija',
            'SE' => 'Švedska',
            'SI' => 'Slovenija',
            'SK' => 'Slovačka',
            'TR' => 'Turska',
            'UA' => 'Ukrajina',
            'US' => 'Sjedinjene Američke Države',
            'UY' => 'Urugvaj',
            'VE' => 'Venecuela',
            'ZA' => 'Južna Afrika',
        ];
    }

    public static function players_dropdown_admin($name, $selected, $club_id, $required)
    {
        $selected = (int) $selected;
        $args = [
            'post_type' => 'igrac',
            'numberposts' => 900,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ];
        $club_id = (int) $club_id;
        if ($club_id > 0) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => 'povezani_klub',
                    'value' => $club_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => 'klub_igraca',
                    'value' => $club_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ];
        }
        $rows = get_posts($args) ?: [];
        $selected_present = false;
        foreach ($rows as $r) {
            if ((int) $r->ID === $selected) {
                $selected_present = true;
                break;
            }
        }
        if ($selected > 0 && !$selected_present) {
            $selected_post = get_post($selected);
            if ($selected_post && $selected_post->post_type === 'igrac') {
                $rows[] = $selected_post;
            }
        }

        $attr = $required ? ' required' : '';
        $select_id = 'stkb_' . preg_replace('/[^a-z0-9_]+/i', '_', (string) $name);
        $select_id = trim((string) $select_id, '_');
        if ($select_id === '') {
            $select_id = 'stkb_player_' . wp_unique_id();
        }
        $html = '<div class="stkb-player-field">';
        $html .= '<div class="stkb-player-field-main">';
        $html .= '<select id="' . esc_attr($select_id) . '" class="stkb-player-select" name="' . esc_attr((string) $name) . '"' . $attr . '>';
        $html .= '<option value="">— izaberi —</option>';
        foreach ($rows as $r) {
            $label = (string) $r->post_title;
            if ((int) $r->ID === $selected && $club_id > 0) {
                $player_club = self::get_player_club_id((int) $r->ID);
                if ($player_club > 0 && $player_club !== (int) $club_id) {
                    $label .= ' (van trenutnog kluba)';
                }
            }
            $html .= '<option value="' . (int) $r->ID . '" ' . selected($selected, (int) $r->ID, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        $html .= '<button type="button" class="button stkb-player-picker-open" data-target-select="' . esc_attr($select_id) . '">Lista igrača</button>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    public static function all_players_admin_index()
    {
        $rows = get_posts([
            'post_type' => 'igrac',
            'numberposts' => 2000,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        if (empty($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $player_id = (int) $r->ID;
            $club_id = self::get_player_club_id($player_id);
            $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '';
            $out[] = [
                'id' => $player_id,
                'name' => (string) $r->post_title,
                'club' => $club_name,
            ];
        }
        return $out;
    }

    public static function get_player_club_id($player_id)
    {
        $player_id = (int) $player_id;
        if ($player_id <= 0) {
            return 0;
        }

        $club_id = OpenTT_Unified_Readonly_Helpers::extract_id(get_post_meta($player_id, 'povezani_klub', true));
        if ($club_id > 0) {
            return $club_id;
        }

        $club_id = OpenTT_Unified_Readonly_Helpers::extract_id(get_post_meta($player_id, 'klub_igraca', true));
        if ($club_id > 0) {
            return $club_id;
        }

        return 0;
    }

    private static function codepoint_to_utf8($cp)
    {
        $cp = intval($cp);
        if ($cp <= 0x7F) {
            return chr($cp);
        }
        if ($cp <= 0x7FF) {
            return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        }
        if ($cp <= 0xFFFF) {
            return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        if ($cp <= 0x10FFFF) {
            return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        return '';
    }
}
