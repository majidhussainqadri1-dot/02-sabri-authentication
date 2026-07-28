<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( isset( $_GET['sa_notice'], $_GET['sa_msg'] ) ) : ?>
	<?php $notice_type = 'success' === sanitize_key( $_GET['sa_notice'] ) ? 'success' : 'error'; ?>
	<div class="sa-notice sa-notice-<?php echo esc_attr( $notice_type ); ?>" role="status"><?php echo esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['sa_msg'] ) ) ) ); ?></div>
<?php endif; ?>

