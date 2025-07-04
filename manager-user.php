<?php
/*
Plugin Name: Manager gebruiker (Publiek.com)
Plugin URI: https://publiek.com
Description: Deze plugin voegt het gebruikerstype 'Manager' toe en stelt daarvoor speciale rechten in.
Author: Mattijs Wit
Version: 1.2
Author URI: https://functioneelwit.nl
*/

require_once dirname(__FILE__) . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

function mijn_plugin_updates_instellen() {
    $updateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/functioneelwit/wp-manager-role/',
        __FILE__, // Let op: voor plugins gebruiken we __FILE__
        'wp-manager-role'
    );

    // Specifiek de main branch volgen
    $updateChecker->setBranch('main');
}
add_action('init', 'mijn_plugin_updates_instellen');


function add_manager_role()
{

        // Get editor role capabilities
        $editor = get_role('editor');
        $editor_caps = $editor->capabilities;

        // Voeg de nieuwe rol toe met editor capabilities
        $manager = add_role(
            'manager',
            __('Manager'),
            $editor_caps
        );

        if ($manager) {
            // Custom post type capabilities
            $custom_caps = array();

            // Admin capabilities
            $admin_caps = array(
                'manage_options',
                'list_users',
                'edit_users',
                'create_users',
                'delete_users',
                'promote_users',
                'edit_theme_options',
                'manage_categories',
                'import',
                'export'
            );

            foreach (array_merge($custom_caps, $admin_caps) as $cap) {
                $manager->add_cap($cap);
            }
        }
    }

add_action('init', 'add_manager_role');

// Beperk welke rollen een manager kan toewijzen/bewerken
function restrict_user_role_options($roles)
{
    if (!current_user_can('administrator')) {
        // Verwijder administrator uit de lijst van beschikbare rollen
        unset($roles['administrator']);
    }
    return $roles;
}
add_filter('editable_roles', 'restrict_user_role_options');

// Extra veiligheid: voorkom dat managers administrator-rechten kunnen toewijzen
function prevent_admin_promotion($caps, $cap, $user_id, $args)
{
    // Controleer alleen voor promote_user capability
    if ($cap === 'promote_user') {
        // Haal de huidige gebruiker op
        $current_user = wp_get_current_user();

        // Als het een manager is
        if (in_array('manager', (array)$current_user->roles)) {
            // Controleer of ze proberen iemand administrator te maken
            if (isset($args[0]) && isset($args[1]) && $args[1] === 'administrator') {
                // Weiger de capability
                $caps[] = 'do_not_allow';
            }
        }
    }
    return $caps;
}
add_filter('map_meta_cap', 'prevent_admin_promotion', 10, 4);

// Verberg administrator gebruikers van de gebruikerslijst voor managers
function hide_admins_from_user_list($query)
{
    if (!current_user_can('administrator')) {
        global $wpdb;

        // Voeg waar-clausule toe om administrators te verbergen
        $query->query_where .= " AND {$wpdb->users}.ID NOT IN (
            SELECT {$wpdb->usermeta}.user_id
            FROM {$wpdb->usermeta}
            WHERE {$wpdb->usermeta}.meta_key = '{$wpdb->prefix}capabilities'
            AND {$wpdb->usermeta}.meta_value LIKE '%administrator%'
        )";
    }
}
add_action('pre_user_query', 'hide_admins_from_user_list');

// Pas de volgorde van de rollen aan
function reorder_user_roles($roles) {
    if (!current_user_can('administrator')) {
        return $roles; // Behoud standaard volgorde voor niet-admins
    }

    // Tijdelijk opslaan van de rollen die we willen herschikken
    $admin = isset($roles['administrator']) ? $roles['administrator'] : null;
    $manager = isset($roles['manager']) ? $roles['manager'] : null;
    $editor = isset($roles['editor']) ? $roles['editor'] : null;

    // Verwijder ze uit de originele array
    unset($roles['administrator'], $roles['manager'], $roles['editor']);

    // Voeg ze weer toe in de gewenste volgorde
    $new_roles = [];
    if ($admin) $new_roles['administrator'] = $admin;
    if ($manager) $new_roles['manager'] = $manager;    // Manager direct na Administrator
    if ($editor) $new_roles['editor'] = $editor;      // Editor komt na Manager

    // Voeg de overige rollen weer toe
    return array_merge($new_roles, $roles);
}
add_filter('editable_roles', 'reorder_user_roles', 20);