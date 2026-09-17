<?php
declare(strict_types=1);

namespace WordPressFetch\Import;

use WordPressFetch\Security\UrlGuard;

final class MediaHandler {
	public function __construct( private readonly UrlGuard $guard ) {}

	public function sideload( string $url, int $post_id, string $alt = '' ): int|\WP_Error {
		if ( ! $this->guard->validate( $url ) ) {
			return new \WP_Error( 'wpfetch_unsafe_media', __( 'The media URL is not safe.', 'wordpress-fetch' ) ); }
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_wpfetch_remote_url_hash',
				'meta_value'     => hash( 'sha256', $url ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( $existing ) {
			return (int) $existing[0]; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url, 20 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp; }
		$settings = wp_parse_args( get_option( 'wpfetch_settings', array() ), \WordPressFetch\Core\Activator::defaults() );
		if ( filesize( $tmp ) > (int) $settings['max_media_bytes'] ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'wpfetch_media_large', __( 'The media file exceeds the configured size limit.', 'wordpress-fetch' ) ); }
		$filename = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$check    = wp_check_filetype_and_ext( $tmp, $filename );
		$allowed  = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'audio/mpeg', 'audio/ogg', 'video/mp4' );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], $allowed, true ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'wpfetch_media_type', __( 'The media file type was rejected.', 'wordpress-fetch' ) ); }
		$file          = array(
			'name'     => $check['proper_filename'] ? $check['proper_filename'] : $filename,
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id; }
		update_post_meta( $attachment_id, '_wpfetch_remote_url_hash', hash( 'sha256', $url ) );
		update_post_meta( $attachment_id, '_wpfetch_remote_url', esc_url_raw( $url ) );
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) ); }
		return $attachment_id;
	}
}
