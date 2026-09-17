<?php
declare(strict_types=1);

namespace WordPressFetch\Editor;

use WordPressFetch\Core\Capabilities;

final class Integration {
	public function hooks(): void {
		add_action( 'init', array( $this, 'registerMeta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editorAssets' ) );
		add_action( 'add_meta_boxes', array( $this, 'metaBox' ) );
		add_action( 'save_post', array( $this, 'saveMetaBox' ) );
		add_filter( 'the_content', array( $this, 'attribution' ) ); }
	public function registerMeta(): void {
		foreach ( array(
			'_wpfetch_sync_locked'      => 'boolean',
			'_wpfetch_protected_fields' => 'array',
		) as $key => $type ) {
			register_post_meta(
				'',
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => 'array' === $type ? array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					) : true,
					'auth_callback'     => static fn( bool $allowed, string $meta_key, int $object_id ): bool => current_user_can( 'edit_post', $object_id ),
					'sanitize_callback' => 'array' === $type ? static fn( $value ): array => array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), array( 'title', 'content', 'excerpt', 'featured_image', 'taxonomies', 'seo' ) ) ) : 'rest_sanitize_boolean',
				)
			); } }
	public function editorAssets(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! get_post_meta( (int) ( $_GET['post'] ?? 0 ), '_wpfetch_source_id', true ) ) {
			return;
		} wp_enqueue_script( 'wpfetch-editor', WPFETCH_URL . 'assets/dist/editor.js', array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ), WPFETCH_VERSION, true ); }
	public function metaBox(): void {
		foreach ( get_post_types( array( 'show_ui' => true ) ) as $type ) {
			add_meta_box( 'wpfetch-import', __( 'Imported content', 'wordpress-fetch' ), array( $this, 'renderMetaBox' ), $type, 'side', 'default' ); } }
	public function renderMetaBox( \WP_Post $post ): void {
		$source_id = (int) get_post_meta( $post->ID, '_wpfetch_source_id', true );
		if ( ! $source_id ) {
			echo '<p>' . esc_html__( 'This post was not imported by WordPress Fetch.', 'wordpress-fetch' ) . '</p>';
			return;
		} wp_nonce_field( 'wpfetch_editor_' . $post->ID, 'wpfetch_editor_nonce' );
		$locked = (bool) get_post_meta( $post->ID, '_wpfetch_sync_locked', true );
		$url    = get_post_meta( $post->ID, '_wpfetch_original_url', true );
		echo '<p><strong>' . esc_html__( 'Source ID:', 'wordpress-fetch' ) . '</strong> ' . esc_html( (string) $source_id ) . '</p>';
		if ( $url ) {
			echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url( $url ) . '">' . esc_html__( 'Open original article', 'wordpress-fetch' ) . '</a></p>';
		} echo '<label><input type="checkbox" name="wpfetch_sync_locked" value="1" ' . checked( $locked, true, false ) . '> ' . esc_html__( 'Lock entire post from synchronization', 'wordpress-fetch' ) . '</label>'; }
	public function saveMetaBox( int $post_id ): void {
		$nonce = isset( $_POST['wpfetch_editor_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpfetch_editor_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wpfetch_editor_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		} update_post_meta( $post_id, '_wpfetch_sync_locked', isset( $_POST['wpfetch_sync_locked'] ) ); }
	public function attribution( string $content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		} $url = get_post_meta( get_the_ID(), '_wpfetch_original_url', true );
		if ( ! $url || has_block( 'wordpress-fetch/source-attribution', get_the_ID() ) ) {
			return $content;
		} $source = get_post_meta( get_the_ID(), '_wpfetch_attribution_enabled', true );
		return $source ? $content . '<p class="wpfetch-attribution"><a href="' . esc_url( $url ) . '" rel="noopener noreferrer">' . esc_html__( 'Read the original article', 'wordpress-fetch' ) . '</a></p>' : $content; }
}
