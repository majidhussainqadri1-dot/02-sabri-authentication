<?php
/**
 * F02-X24-023 + F02-NFR-003: real WordPress options-table proof that FedCM
 * replay claims are single-use, non-autoloaded, bounded and cleanup-isolated.
 * Disposable CI WordPress database only.
 */
if ( ! defined('ABSPATH') || ! class_exists('SAUTH_Modern_Auth') ) { exit(2); }
global $wpdb;
$key = 'sauth_fedcm_claim_' . hash_hmac('sha256', 'ci-only-fixed-claim', wp_salt('auth'));
$foreign = 'smc_ci_fedcm_foreign_option';
try {
    delete_option($key);
    delete_option($foreign);
    if ( ! add_option($key, time(), '', false) ) { throw new RuntimeException('first claim not created'); }
    if ( add_option($key, time(), '', false) ) { throw new RuntimeException('duplicate once-only claim accepted'); }
    $row = $wpdb->get_row($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name=%s", $key), ARRAY_A);
    if (!is_array($row) || in_array((string)$row['autoload'], array('yes','on','auto-on'),true)) {
        throw new RuntimeException('replay claim would be autoloaded');
    }
    do_action('sauth_fedcm_claim_expire', $key);
    if (false === get_option($key,false)) { throw new RuntimeException('fresh claim removed'); }
    update_option($key, time()-601, false);
    do_action('sauth_fedcm_claim_expire', $key);
    if(false !== get_option($key,false)){throw new RuntimeException('expired claim retained');}
    add_option($foreign, 'preserve', '', false);
    do_action('sauth_fedcm_claim_expire', $foreign);
    if ('preserve' !== get_option($foreign,'')) { throw new RuntimeException('foreign option deleted'); }
    echo "PASS: FedCM one-time option claim and bounded cleanup on real WordPress/MariaDB.\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");
    exit(1);
} finally {
    delete_option($key);
    delete_option($foreign);
}
