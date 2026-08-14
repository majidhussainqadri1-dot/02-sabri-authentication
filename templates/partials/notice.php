<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( isset( $_GET['sa_notice'], $_GET['sa_msg'], $_GET['sa_sig'] ) ) : ?>
	<?php
	$notice_type = 'success' === sanitize_key( wp_unslash( $_GET['sa_notice'] ) ) ? 'success' : 'error';
	$notice_text = sanitize_text_field( wp_unslash( $_GET['sa_msg'] ) );
	$notice_sig  = sanitize_text_field( wp_unslash( $_GET['sa_sig'] ) );
	?>
	<?php if ( SA_Security::notice_valid( $notice_type, $notice_text, $notice_sig ) ) : ?>
		<div class="sa-notice sa-notice-<?php echo esc_attr( $notice_type ); ?>" role="status"><?php echo esc_html( $notice_text ); ?></div>
	<?php endif; ?>
<?php endif; ?>
