<?php defined( 'ABSPATH' ) || exit; ?>
<?php $sauth_notice = SA_Security::request_notice(); ?>
<?php if ( ! empty( $sauth_notice ) ) : ?>
<div class="sa-notice sa-notice-<?php echo esc_attr( $sauth_notice['type'] ); ?>" role="status"><?php echo esc_html( $sauth_notice['message'] ); ?></div>
<?php endif; ?>
