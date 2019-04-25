<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Quidquid agis, prudenter agas et respice finem!');
}

delete_option('produck_config');
?>