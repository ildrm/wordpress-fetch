<?php
declare(strict_types=1);

namespace WordPressFetch\Api;

use WordPressFetch\Core\Capabilities;
use WordPressFetch\Feed\Discovery;
use WordPressFetch\Feed\HttpClient;
use WordPressFetch\Feed\Opml;
use WordPressFetch\Feed\Parser;
use WordPressFetch\Infrastructure\Database\Schema;
use WordPressFetch\Queue\Queue;
use WordPressFetch\Repository\ConfigRepository;
use WordPressFetch\Repository\SourceRepository;
use WordPressFetch\Security\UrlGuard;
use WordPressFetch\Seo\Manager;

final class Controller extends \WP_REST_Controller {
	protected $namespace = 'wordpress-fetch/v1';
	public function __construct( private readonly SourceRepository $sources, private readonly ConfigRepository $configs, private readonly Queue $queue, private readonly HttpClient $http, private readonly Parser $parser, private readonly Discovery $discovery, private readonly Opml $opml, private readonly UrlGuard $guard, private readonly Manager $seo ) {}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/sources',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'listSources' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_SOURCES ),
					'args'                => $this->pageArgs(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'createSource' ),
					'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/sources/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getSource' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_SOURCES ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'updateSource' ),
					'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'deleteSource' ),
					'permission_callback' => $this->can( Capabilities::DELETE_SOURCES ),
				),
			),
			false
		);
		register_rest_route(
			$this->namespace,
			'/sources/(?P<id>\d+)/(?P<action>fetch|dry-run|test)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sourceAction' ),
				'permission_callback' => $this->can( Capabilities::RUN_IMPORTS ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/sources/(?P<id>\d+)/clone',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cloneSource' ),
				'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/sources/bulk',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'bulkSources' ),
				'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'testUrl' ),
				'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
				'args'                => array(
					'url' => array(
						'required' => true,
						'type'     => 'string',
						'format'   => 'uri',
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/discover',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'discover' ),
				'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
				'args'                => array(
					'url' => array(
						'required' => true,
						'type'     => 'string',
						'format'   => 'uri',
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/metadata',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'metadata' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_SOURCES ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/dashboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_SOURCES ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/(?P<action>retry|cancel)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'jobAction' ),
				'permission_callback' => $this->can( Capabilities::RUN_IMPORTS ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'logs' ),
				'permission_callback' => $this->can( Capabilities::VIEW_LOGS ),
				'args'                => $this->pageArgs(),
			)
		);
		register_rest_route(
			$this->namespace,
			'/opml/parse',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'parseOpml' ),
				'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/opml/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'importOpml' ),
				'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/opml/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'exportOpml' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_SOURCES ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/(?P<entity>groups|profiles)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'listConfigs' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_SOURCES ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveConfig' ),
					'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/(?P<entity>groups|profiles)/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'saveConfig' ),
					'permission_callback' => $this->can( Capabilities::EDIT_SOURCES ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'deleteConfig' ),
					'permission_callback' => $this->can( Capabilities::DELETE_SOURCES ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/configuration/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'exportConfiguration' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_SETTINGS ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/configuration/validate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validateConfiguration' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_SETTINGS ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/configuration/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'importConfiguration' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_SETTINGS ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( get_option( 'wpfetch_settings', array() ) ),
					'permission_callback' => $this->can( Capabilities::MANAGE_SETTINGS ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'saveSettings' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_SETTINGS ),
				),
			)
		);
	}

	/** @return callable */ private function can( string $capability ): callable {
		return static fn(): bool => current_user_can( $capability ); }
	/** @return array<string,array<string,mixed>> */ private function pageArgs(): array {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
		); }

	public function listSources( \WP_REST_Request $request ): \WP_REST_Response {
		$data     = $this->sources->page( (int) $request['page'], (int) $request['per_page'], (string) $request['search'], (string) $request['status'] );
		$response = rest_ensure_response( $data['items'] );
		$response->header( 'X-WP-Total', (string) $data['total'] );
		$response->header( 'X-WP-TotalPages', (string) ceil( $data['total'] / max( 1, (int) $request['per_page'] ) ) );
		return $response; }
	public function getSource( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$source = $this->sources->find( (int) $request['id'] );
		if ( ! $source ) {
			return new \WP_Error( 'wpfetch_not_found', __( 'Source not found.', 'wordpress-fetch' ), array( 'status' => 404 ) );
		} return rest_ensure_response(
			array(
				'id'                     => $source->id,
				'name'                   => $source->name,
				'feed_url'               => $source->feedUrl,
				'post_type'              => $source->postType,
				'post_status'            => $source->postStatus,
				'author_id'              => $source->authorId,
				'config'                 => $source->config,
				'credentials_configured' => ! empty( $source->credentials ),
			)
		); }
	public function createSource( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $this->validatedSource( $request->get_json_params() );
		if ( is_wp_error( $data ) ) {
			return $data;
		} $id = $this->sources->save( $data );
		return new \WP_REST_Response( array( 'id' => $id ), 201 ); }
	public function updateSource( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->sources->find( (int) $request['id'] ) ) {
			return new \WP_Error( 'wpfetch_not_found', __( 'Source not found.', 'wordpress-fetch' ), array( 'status' => 404 ) );
		} $data = $this->validatedSource( $request->get_json_params() );
		if ( is_wp_error( $data ) ) {
			return $data;
		} return rest_ensure_response( array( 'id' => $this->sources->save( $data, (int) $request['id'] ) ) ); }
	public function deleteSource( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'deleted'         => $this->sources->delete( (int) $request['id'] ),
				'posts_preserved' => true,
			)
		); }

	public function sourceAction( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$source = $this->sources->find( (int) $request['id'] );
		if ( ! $source ) {
			return new \WP_Error( 'wpfetch_not_found', __( 'Source not found.', 'wordpress-fetch' ), array( 'status' => 404 ) );
		} if ( 'test' === $request['action'] ) {
			return $this->testFeed( $source->feedUrl, $source );
		} $id = $this->queue->enqueue( 'dry-run' === $request['action'] ? 'dry_run' : 'fetch', array(), $source->id, 1 );
		return new \WP_REST_Response(
			array(
				'job_id' => $id,
				'status' => 'pending',
			),
			202
		); }
	public function cloneSource( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->sources->clone( (int) $request['id'] );
		return $id ? new \WP_REST_Response(
			array(
				'id'                 => $id,
				'status'             => 'disabled',
				'credentials_copied' => false,
			),
			201
		) : new \WP_Error( 'wpfetch_not_found', __( 'Source not found.', 'wordpress-fetch' ), array( 'status' => 404 ) ); }
	public function bulkSources( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) );
		if ( ! $ids || count( $ids ) > 100 ) {
			return new \WP_Error( 'wpfetch_bulk_ids', __( 'Select between 1 and 100 sources.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		} $action = (string) $request->get_param( 'action' );
		if ( 'fetch' === $action ) {
			foreach ( $ids as $id ) {
				$this->queue->enqueue( 'fetch', array(), $id );
			} return rest_ensure_response( array( 'queued' => count( $ids ) ) );
		} $status = array(
			'enable'  => 'enabled',
			'disable' => 'disabled',
			'archive' => 'archived',
		)[ $action ] ?? '';
		if ( ! $status ) {
			return new \WP_Error( 'wpfetch_bulk_action', __( 'Unsupported bulk action.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		}
		$updated = 0;
		foreach ( $ids as $id ) {
			$result = $wpdb->update(
				Schema::table( 'sources' ),
				array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false !== $result ) {
				$updated += $result;
			}
		}
		return rest_ensure_response( array( 'updated' => (int) $updated ) ); }
	public function testUrl( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params      = $request->get_json_params();
		$credentials = is_array( $params['secret'] ?? null ) ? $params['secret'] : array();
		$temporary   = $credentials ? new \WordPressFetch\Domain\Source( 0, __( 'Connection test', 'wordpress-fetch' ), (string) $request['url'], 'post', 'draft', 1, array(), null, null, $credentials ) : null;
		return $this->testFeed( (string) $request['url'], $temporary ); }
	private function testFeed( string $url, ?\WordPressFetch\Domain\Source $source = null ): \WP_REST_Response|\WP_Error {
		$response = $this->http->fetch( $url, $source );
		if ( is_wp_error( $response ) ) {
			return $response;
		} $feed = $this->parser->parse( $response['body'] );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		} return rest_ensure_response(
			array(
				'connectivity'      => true,
				'http_status'       => $response['status'],
				'response_time_ms'  => $response['duration_ms'],
				'content_type'      => $response['headers']['content-type'] ?? '',
				'detected_format'   => $feed['type'],
				'title'             => $feed['title'],
				'description'       => $feed['description'],
				'entry_count'       => count( $feed['items'] ),
				'latest_entry_date' => $feed['items'][0]->publishedAt?->format( DATE_ATOM ),
				'namespaces'        => $feed['namespaces'],
				'etag'              => $response['headers']['etag'] ?? '',
				'last_modified'     => $response['headers']['last-modified'] ?? '',
				'items'             => array_slice( $feed['items'], 0, 5 ),
			)
		); }
	public function discover( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->discovery->discover( (string) $request['url'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }

	public function metadata(): \WP_REST_Response {
		$types = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( ! $type->cap->create_posts || ! current_user_can( $type->cap->create_posts ) ) {
				continue;
			} $taxonomies = array();
			foreach ( get_object_taxonomies( $type->name, 'objects' ) as $tax ) {
				$taxonomies[] = array(
					'slug'         => $tax->name,
					'label'        => $tax->labels->singular_name,
					'hierarchical' => $tax->hierarchical,
				);
			} $types[] = array(
				'slug'         => $type->name,
				'label'        => $type->labels->singular_name,
				'public'       => $type->public,
				'hierarchical' => $type->hierarchical,
				'taxonomies'   => $taxonomies,
			);
		} return rest_ensure_response(
			array(
				'post_types'      => $types,
				'statuses'        => array( 'draft', 'pending', 'publish', 'private' ),
				'schedules'       => array( 'manual', 'wpfetch_5min', 'wpfetch_15min', 'wpfetch_30min', 'hourly', 'twicedaily', 'daily', 'weekly' ),
				'seo_provider'    => $this->seo->provider(),
				'template_tokens' => \WordPressFetch\Import\TemplateEngine::TOKENS,
			)
		); }

	public function dashboard(): \WP_REST_Response {
		global $wpdb;
		$sources = Schema::table( 'sources' );
		$imports = Schema::table( 'imports' );
		$metrics = array(
			'sources'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sources}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
			'healthy'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sources} WHERE health=%s", 'healthy' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
			'warnings'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sources} WHERE health NOT IN (%s,%s,%s)", 'healthy', 'unknown', 'disabled' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
			'disabled'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sources} WHERE status=%s", 'disabled' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
			'imports_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$imports} WHERE state=%s AND first_imported_at >= UTC_DATE()", 'imported' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
			'updates_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$imports} WHERE state=%s AND last_synced_at >= UTC_DATE()", 'updated' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
		);
		return rest_ensure_response(
			array(
				'metrics'      => $metrics,
				'queue'        => $this->queue->counts(),
				'seo_provider' => $this->seo->provider(),
				'cron_next'    => wp_next_scheduled( \WordPressFetch\Scheduling\Scheduler::HOOK ) ? wp_next_scheduled( \WordPressFetch\Scheduling\Scheduler::HOOK ) : null,
			)
		); }

	public function jobAction( \WP_REST_Request $request ): \WP_REST_Response {
		$ok = 'retry' === $request['action'] ? $this->queue->retry( (int) $request['id'] ) : $this->queue->cancel( (int) $request['id'] );
		return rest_ensure_response( array( 'success' => $ok ) ); }
	public function logs( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$table  = Schema::table( 'logs' );
		$limit  = (int) $request['per_page'];
		$offset = ( (int) $request['page'] - 1 ) * $limit;
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id,source_id,job_id,severity,event,message,created_at FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
		return rest_ensure_response( $rows ? $rows : array() ); }
	public function parseOpml( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->opml->parse( (string) $request->get_param( 'opml' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }
	public function importOpml( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$feeds = $request->get_param( 'feeds' );
		if ( ! is_array( $feeds ) || count( $feeds ) > 1000 ) {
			return new \WP_Error( 'wpfetch_opml_selection', __( 'Select between 1 and 1,000 OPML sources.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		} $defaults = is_array( $request->get_param( 'defaults' ) ) ? $request->get_param( 'defaults' ) : array();
		$ids        = array();
		foreach ( $feeds as $feed ) {
			if ( ! is_array( $feed ) || ! $this->guard->validate( (string) ( $feed['feed_url'] ?? '' ) ) ) {
				continue;
			} $ids[] = $this->sources->save(
				array_merge(
					$defaults,
					array(
						'name'        => $feed['title'] ?? '',
						'feed_url'    => $feed['feed_url'],
						'website_url' => $feed['website_url'] ?? '',
						'status'      => 'disabled',
					)
				)
			);
		} return new \WP_REST_Response(
			array(
				'created' => count( $ids ),
				'ids'     => $ids,
			),
			201
		); }
	public function exportOpml(): \WP_REST_Response {
		$page = $this->sources->page( 1, 1000 );
		return rest_ensure_response(
			array(
				'filename' => 'wordpress-fetch-sources.opml',
				'opml'     => $this->opml->export( $page['items'] ),
			)
		); }
	public function listConfigs( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( $this->configs->all( (string) $request['entity'], sanitize_key( (string) $request->get_param( 'type' ) ) ) ); }
	public function saveConfig( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $request->get_json_params();
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'wpfetch_config_name', __( 'A name is required.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		} $id = $this->configs->save( (string) $request['entity'], $data, (int) ( $request['id'] ?? 0 ) );
		return new \WP_REST_Response( array( 'id' => $id ), empty( $request['id'] ) ? 201 : 200 ); }
	public function deleteConfig( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( array( 'deleted' => $this->configs->delete( (string) $request['entity'], (int) $request['id'] ) ) ); }
	public function exportConfiguration(): \WP_REST_Response {
		$service = new \WordPressFetch\Export\Configuration( $this->sources, $this->configs );
		return rest_ensure_response( $service->export() ); }
	public function validateConfiguration( \WP_REST_Request $request ): \WP_REST_Response {
		$service = new \WordPressFetch\Export\Configuration( $this->sources, $this->configs );
		$errors  = $service->validate( $request->get_json_params() );
		return rest_ensure_response(
			array(
				'valid'   => ! $errors,
				'errors'  => $errors,
				'planned' => array(
					'sources'     => count( (array) ( $request->get_json_params()['sources'] ?? array() ) ),
					'credentials' => 'omitted',
				),
			)
		); }

	public function importConfiguration( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data    = $request->get_json_params();
		$service = new \WordPressFetch\Export\Configuration( $this->sources, $this->configs );
		$errors  = $service->validate( $data );
		if ( $errors ) {
			return new \WP_Error( 'wpfetch_configuration_invalid', implode( ' ', $errors ), array( 'status' => 400 ) ); }
		if ( true !== $request->get_param( 'confirm' ) ) {
			return rest_ensure_response(
				array(
					'applied' => false,
					'planned' => array(
						'sources'  => count( $data['sources'] ),
						'groups'   => count( (array) ( $data['groups'] ?? array() ) ),
						'profiles' => count( (array) ( $data['profiles'] ?? array() ) ),
					),
				)
			);
		}
		$created = array(
			'sources'  => 0,
			'groups'   => 0,
			'profiles' => 0,
		);
		foreach ( (array) ( $data['groups'] ?? array() ) as $group ) {
			if ( is_array( $group ) && ! empty( $group['name'] ) ) {
				$this->configs->save( 'groups', $group );
				++$created['groups']; }
		}
		foreach ( (array) ( $data['profiles'] ?? array() ) as $profile ) {
			if ( is_array( $profile ) && ! empty( $profile['name'] ) ) {
				$this->configs->save( 'profiles', $profile );
				++$created['profiles']; }
		}
		foreach ( $data['sources'] as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			} $valid = $this->validatedSource( $source );
			if ( is_wp_error( $valid ) ) {
				continue;
			} $valid['status'] = 'disabled';
			unset( $valid['secret'] );
			$this->sources->save( $valid );
			++$created['sources']; }
		return new \WP_REST_Response(
			array(
				'applied'              => true,
				'created'              => $created,
				'credentials_imported' => false,
			),
			201
		);
	}

	public function saveSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$input    = $request->get_json_params();
		$defaults = \WordPressFetch\Core\Activator::defaults();
		$output   = $defaults;
		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			} $output[ $key ] = is_int( $default ) ? absint( $input[ $key ] ) : sanitize_text_field( (string) $input[ $key ] );
		}
		$ranges = array(
			'queue_batch_size'   => array( 1, 100 ),
			'max_feed_bytes'     => array( 65536, 20971520 ),
			'max_items_fetch'    => array( 1, 5000 ),
			'max_imports_run'    => array( 1, 1000 ),
			'max_media_bytes'    => array( 65536, 104857600 ),
			'request_timeout'    => array( 3, 30 ),
			'log_retention_days' => array( 1, 365 ),
		);
		foreach ( $ranges as $key => $range ) {
			$output[ $key ] = min( $range[1], max( $range[0], (int) $output[ $key ] ) ); }
		$output['uninstall_policy'] = in_array( $output['uninstall_policy'], array( 'preserve', 'config_only', 'full_cleanup' ), true ) ? $output['uninstall_policy'] : 'preserve';
		if ( isset( $input['notification_email'] ) ) {
			$output['notification_email'] = sanitize_email( (string) $input['notification_email'] );
		} update_option( 'wpfetch_settings', $output, false );
		return rest_ensure_response( $output ); }

	/**
	 * @param array<string,mixed> $data Source request data.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function validatedSource( array $data ): array|\WP_Error {
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'wpfetch_name_required', __( 'Give the source a name.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		} if ( empty( $data['feed_url'] ) || ! $this->guard->validate( (string) $data['feed_url'] ) ) {
			return new \WP_Error( 'wpfetch_feed_url', __( 'Enter a public HTTP or HTTPS feed URL.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		} $type = sanitize_key( (string) ( $data['post_type'] ?? 'post' ) );
		$object = get_post_type_object( $type );
		if ( ! $object || ! current_user_can( $object->cap->create_posts ) ) {
			return new \WP_Error( 'wpfetch_post_type', __( 'Select a writable destination post type.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		} $config = is_array( $data['config'] ?? null ) ? $data['config'] : array();
		$secret   = is_array( $data['secret'] ?? null ) ? $data['secret'] : array();
		if ( $secret && 'none' !== ( $secret['type'] ?? 'none' ) && ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return new \WP_Error( 'wpfetch_crypto_unavailable', __( 'Libsodium is required before authenticated sources can be saved.', 'wordpress-fetch' ), array( 'status' => 503 ) );
		}
		if ( 'basic' === ( $secret['type'] ?? '' ) && ( empty( $secret['username'] ) || empty( $secret['password'] ) ) ) {
			return new \WP_Error( 'wpfetch_basic_auth', __( 'Basic authentication requires both username and password.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		}
		if ( 'bearer' === ( $secret['type'] ?? '' ) && empty( $secret['token'] ) ) {
			return new \WP_Error( 'wpfetch_bearer_auth', __( 'Bearer authentication requires a token.', 'wordpress-fetch' ), array( 'status' => 400 ) );
		}
		if ( 'template' === ( $config['content_mode'] ?? '' ) ) {
			$unknown = ( new \WordPressFetch\Import\TemplateEngine() )->unknownTokens( (string) ( $config['content_template'] ?? '' ) );
			if ( $unknown ) {
				/* translators: %s: comma-separated template tokens. */
				return new \WP_Error( 'wpfetch_template_tokens', sprintf( __( 'Unknown template token(s): %s', 'wordpress-fetch' ), implode( ', ', $unknown ) ), array( 'status' => 400 ) );
			}
		} $data['post_type'] = $type;
		$data['config']      = $config;
		return $data; }
}
