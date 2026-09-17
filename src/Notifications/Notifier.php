<?php
declare(strict_types=1);

namespace WordPressFetch\Notifications;

final class Notifier {
	public function notify( string $event, string $subject, string $message, int $cooldown = 3600 ): void {
		$key = 'wpfetch_notice_' . md5( $event );
		if ( get_transient( $key ) ) {
			return;
		} set_transient(
			$key,
			array(
				'subject' => sanitize_text_field( $subject ),
				'message' => sanitize_textarea_field( $message ),
			),
			$cooldown
		);
		$notices   = get_option( 'wpfetch_admin_notices', array() );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'subject' => sanitize_text_field( $subject ),
			'message' => sanitize_textarea_field( $message ),
			'time'    => time(),
		);
		update_option( 'wpfetch_admin_notices', array_slice( $notices, -20 ), false );
		$settings = get_option( 'wpfetch_settings', array() );
		if ( ! empty( $settings['notification_email'] ) ) {
			wp_mail( sanitize_email( (string) $settings['notification_email'] ), $subject, $message ); }
	}

	public function renderAdminNotices(): void {
		if ( ! current_user_can( \WordPressFetch\Core\Capabilities::MANAGE_SOURCES ) ) {
			return; }
		$notices = get_option( 'wpfetch_admin_notices', array() );
		if ( ! is_array( $notices ) || ! $notices ) {
			return;
		}
		foreach ( $notices as $notice ) {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html( (string) ( $notice['subject'] ?? '' ) ) . '</strong><br>' . esc_html( (string) ( $notice['message'] ?? '' ) ) . '</p></div>';
		}
		delete_option( 'wpfetch_admin_notices' );
	}
}
