<?php
// Controleer of WordPress deze uninstall aanroept
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Vind alle gebruikers met manager rol
$managers = get_users(['role' => 'manager']);

// Verander hun rol naar editor
foreach($managers as $user) {
    $user->set_role('editor');
}

// Verwijder de manager rol
remove_role('manager');