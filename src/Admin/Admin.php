<?php
declare(strict_types=1);

namespace WordPressFetch\Admin;

use WordPressFetch\Core\Capabilities;
use WordPressFetch\Seo\Manager;

final class Admin {
	public function __construct( private readonly Manager $seo ) {}
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPFETCH_FILE ), array( $this, 'actionLinks' ) ); }

	public function menu(): void {
		add_menu_page( __( 'WordPress Fetch', 'wordpress-fetch' ), __( 'WP Fetch', 'wordpress-fetch' ), Capabilities::MANAGE_SOURCES, 'wordpress-fetch', array( $this, 'render' ), 'dashicons-rss', 58 );
		add_submenu_page( 'wordpress-fetch', __( 'Sources', 'wordpress-fetch' ), __( 'Sources', 'wordpress-fetch' ), Capabilities::MANAGE_SOURCES, 'wordpress-fetch-sources', array( $this, 'render' ) );
		add_submenu_page( 'wordpress-fetch', __( 'Add source', 'wordpress-fetch' ), __( 'Add source', 'wordpress-fetch' ), Capabilities::EDIT_SOURCES, 'wordpress-fetch-add', array( $this, 'render' ) );
		add_submenu_page( 'wordpress-fetch', __( 'Activity', 'wordpress-fetch' ), __( 'Activity', 'wordpress-fetch' ), Capabilities::VIEW_LOGS, 'wordpress-fetch-activity', array( $this, 'render' ) );
		add_submenu_page( 'wordpress-fetch', __( 'Tools & Settings', 'wordpress-fetch' ), __( 'Tools & Settings', 'wordpress-fetch' ), Capabilities::MANAGE_SETTINGS, 'wordpress-fetch-tools', array( $this, 'render' ) );
	}

	public function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'wordpress-fetch' ) ) {
			return;
		} wp_enqueue_style( 'wpfetch-admin', WPFETCH_URL . 'assets/dist/admin.css', array(), WPFETCH_VERSION );
		wp_enqueue_script( 'wpfetch-admin', WPFETCH_URL . 'assets/dist/admin.js', array( 'wp-api-fetch', 'wp-i18n' ), WPFETCH_VERSION, true );
		wp_localize_script(
			'wpfetch-admin',
			'wpFetchAdmin',
			array(
				'apiRoot'     => esc_url_raw( rest_url( 'wordpress-fetch/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'page'        => sanitize_key( (string) ( $_GET['page'] ?? 'wordpress-fetch' ) ),
				'seoProvider' => $this->seo->provider(),
			)
		); }

	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) && ! current_user_can( Capabilities::EDIT_SOURCES ) && ! current_user_can( Capabilities::VIEW_LOGS ) && ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-fetch' ) ); } ?>
		<div class="wrap wpfetch-admin" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
			<div class="wpfetch-page-header"><div><h1><?php esc_html_e( 'WordPress Fetch', 'wordpress-fetch' ); ?></h1><p><?php esc_html_e( 'Secure feed ingestion with explicit editorial and SEO controls.', 'wordpress-fetch' ); ?></p></div><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wordpress-fetch-add' ) ); ?>"><?php esc_html_e( 'Add source', 'wordpress-fetch' ); ?></a></div>
			<div id="wpfetch-live" class="screen-reader-text" aria-live="polite"></div><div id="wpfetch-app"><div class="wpfetch-skeleton" aria-label="<?php esc_attr_e( 'Loading', 'wordpress-fetch' ); ?>"></div></div>
			<noscript><div class="notice notice-error"><p><?php esc_html_e( 'WordPress Fetch requires JavaScript for its administration screens. Imports continue to run in the background.', 'wordpress-fetch' ); ?></p></div></noscript>
		</div>
		<?php
	}

	/**
	 * @param list<string> $links Existing action links.
	 * @return list<string>
	 */
	public function actionLinks( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wordpress-fetch' ) ) . '">' . esc_html__( 'Dashboard', 'wordpress-fetch' ) . '</a>' );
		return $links; }
}
