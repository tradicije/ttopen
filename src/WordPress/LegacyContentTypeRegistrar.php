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

namespace OpenTT\Unified\WordPress;

final class LegacyContentTypeRegistrar
{
    public static function register()
    {
        if (!post_type_exists('klub')) {
            register_post_type('klub', [
                'labels' => [
                    'name' => 'Klubovi',
                    'singular_name' => 'Klub',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'show_in_menu' => false,
                'has_archive' => false,
                'rewrite' => ['slug' => 'klub', 'with_front' => false],
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            ]);
        }

        if (!post_type_exists('igrac')) {
            register_post_type('igrac', [
                'labels' => [
                    'name' => 'Igrači',
                    'singular_name' => 'Igrač',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'show_in_menu' => false,
                'has_archive' => false,
                'rewrite' => false,
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            ]);
        }

        if (!post_type_exists('liga')) {
            register_post_type('liga', [
                'labels' => [
                    'name' => 'Lige',
                    'singular_name' => 'Liga',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'show_in_menu' => false,
                'has_archive' => false,
                'rewrite' => ['slug' => 'liga', 'with_front' => false],
                'supports' => ['title', 'editor'],
            ]);
        }

        if (!post_type_exists('sezona')) {
            register_post_type('sezona', [
                'labels' => [
                    'name' => 'Sezone',
                    'singular_name' => 'Sezona',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'show_in_menu' => false,
                'has_archive' => false,
                'rewrite' => false,
                'supports' => ['title'],
            ]);
        }

        if (!post_type_exists('pravilo_takmicenja')) {
            register_post_type('pravilo_takmicenja', [
                'labels' => [
                    'name' => 'Pravila takmičenja',
                    'singular_name' => 'Pravilo takmičenja',
                ],
                'public' => false,
                'show_ui' => false,
                'show_in_rest' => false,
                'show_in_menu' => false,
                'has_archive' => false,
                'rewrite' => false,
                'supports' => ['title', 'thumbnail'],
            ]);
        }

        if (!taxonomy_exists('liga_sezona')) {
            register_taxonomy('liga_sezona', ['utakmica'], [
                'labels' => [
                    'name' => 'Lige i sezone',
                    'singular_name' => 'Liga i sezona',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'hierarchical' => false,
                'rewrite' => ['slug' => 'liga', 'with_front' => false],
            ]);
        }

        if (!taxonomy_exists('kolo')) {
            register_taxonomy('kolo', ['utakmica'], [
                'labels' => [
                    'name' => 'Kola',
                    'singular_name' => 'Kolo',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'hierarchical' => false,
                'rewrite' => ['slug' => 'kolo', 'with_front' => false],
            ]);
        }
    }
}
