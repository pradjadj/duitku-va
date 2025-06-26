<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('duitku_settings');

// Delete any other plugin-related options if needed
// delete_option('other_option_name');

?>
