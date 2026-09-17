<?php
declare(strict_types=1);

namespace WordPressFetch\Repository;

use WordPressFetch\Domain\Source;
use WordPressFetch\Infrastructure\Database\Schema;
use WordPressFetch\Security\SecretVault;

final class SourceRepository {
	public function __construct( private readonly SecretVault $vault ) {}

	public function find( int $id ): ?Source {
		global $wpdb;
		$table = Schema::table( 'sources' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/** @return array{items:list<array<string,mixed>>,total:int} */
	public function page( int $page = 1, int $per_page = 20, string $search = '', string $status = '' ): array {
		global $wpdb;
		$table = Schema::table( 'sources' );
		$where = array( '1=1' );
		$args  = array();
		if ( '' !== $search ) {
			$where[] = 'name LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%'; }
		if ( '' !== $status ) {
			$where[] = 'status = %s';
			$args[]  = $status; }
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = (int) $wpdb->get_var( $args ? $wpdb->prepare( $count_sql, $args ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$args[]    = $per_page;
		$args[]    = ( $page - 1 ) * $per_page;
		$query     = "SELECT id,uuid,name,website_url,feed_url,detected_type,group_id,status,post_type,post_status,schedule,next_run,last_fetch,last_success,health,failure_count,created_at,updated_at FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows      = $wpdb->get_results( $wpdb->prepare( $query, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/** @param array<string,mixed> $data */
	public function save( array $data, int $id = 0 ): int {
		global $wpdb;
		$table  = Schema::table( 'sources' );
		$now    = current_time( 'mysql', true );
		$config = isset( $data['config'] ) && is_array( $data['config'] ) ? $data['config'] : array();
		$secret = isset( $data['secret'] ) && is_array( $data['secret'] ) ? $data['secret'] : array();
		$row    = array(
			'name'          => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'website_url'   => esc_url_raw( (string) ( $data['website_url'] ?? '' ) ),
			'feed_url'      => esc_url_raw( (string) ( $data['feed_url'] ?? '' ) ),
			'source_type'   => sanitize_key( (string) ( $data['source_type'] ?? 'auto' ) ),
			'detected_type' => sanitize_key( (string) ( $data['detected_type'] ?? '' ) ),
			'group_id'      => max( 0, (int) ( $data['group_id'] ?? 0 ) ) ? max( 0, (int) ( $data['group_id'] ?? 0 ) ) : null,
			'status'        => in_array( $data['status'] ?? '', array( 'enabled', 'disabled', 'archived' ), true ) ? $data['status'] : 'disabled',
			'language'      => sanitize_text_field( (string) ( $data['language'] ?? '' ) ),
			'timezone'      => sanitize_text_field( (string) ( $data['timezone'] ?? '' ) ),
			'post_type'     => sanitize_key( (string) ( $data['post_type'] ?? 'post' ) ),
			'post_status'   => sanitize_key( (string) ( $data['post_status'] ?? 'draft' ) ),
			'author_id'     => max( 1, (int) ( $data['author_id'] ?? get_current_user_id() ) ),
			'schedule'      => sanitize_key( (string) ( $data['schedule'] ?? 'hourly' ) ),
			'config'        => wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'updated_at'    => $now,
		);
		if ( $secret ) {
			$encoded_secret = wp_json_encode( $secret );
			$row['secret']  = $this->vault->encrypt( $encoded_secret ? $encoded_secret : '{}' ); }
		if ( $id > 0 ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) );
			return $id;
		}
		$row['uuid']          = wp_generate_uuid4();
		$row['health']        = 'unknown';
		$row['failure_count'] = 0;
		$row['created_at']    = $now;
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( Schema::table( 'sources' ), array( 'id' => $id ), array( '%d' ) );
	}

	public function clone( int $id ): int {
		global $wpdb;
		$table = Schema::table( 'sources' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
		if ( ! is_array( $row ) ) {
			return 0;
		} unset( $row['id'], $row['secret'], $row['last_fetch'], $row['last_success'], $row['etag'], $row['last_modified'] );
		$row['uuid'] = wp_generate_uuid4();
		/* translators: %s: original source name. */
		$row['name']          = sprintf( __( '%s (copy)', 'wordpress-fetch' ), $row['name'] );
		$row['status']        = 'disabled';
		$row['health']        = 'unknown';
		$row['failure_count'] = 0;
		$row['next_run']      = null;
		$row['created_at']    = current_time( 'mysql', true );
		$row['updated_at']    = $row['created_at'];
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/** @return list<array<string,mixed>> */
	public function exportAll(): array {
		global $wpdb;
		$table = Schema::table( 'sources' );
		$rows  = $wpdb->get_results( "SELECT name,website_url,feed_url,source_type,detected_type,status,language,timezone,post_type,post_status,author_id,schedule,config FROM {$table} ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table with no values.
		foreach ( $rows ? $rows : array() as &$row ) {
			$config        = json_decode( (string) $row['config'], true );
			$row['config'] = is_array( $config ) ? $config : array();
		}
		return $rows ? $rows : array();
	}

	/** @param array<string,mixed> $row */
	private function hydrate( array $row ): Source {
		$config = json_decode( (string) $row['config'], true );
		if ( ! empty( $row['group_id'] ) ) {
			global $wpdb;
			$groups       = Schema::table( 'groups' );
			$group_json   = $wpdb->get_var( $wpdb->prepare( "SELECT config FROM {$groups} WHERE id=%d", (int) $row['group_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
			$group_config = is_string( $group_json ) ? json_decode( $group_json, true ) : array();
			if ( is_array( $group_config ) ) {
				$config = array_replace_recursive( $group_config, is_array( $config ) ? $config : array() ); }
		}
		$secret_json = ! empty( $row['secret'] ) ? $this->vault->decrypt( (string) $row['secret'] ) : '';
		$credentials = $secret_json ? json_decode( $secret_json, true ) : array();
		return new Source( (int) $row['id'], (string) $row['name'], (string) $row['feed_url'], (string) $row['post_type'], (string) $row['post_status'], (int) $row['author_id'], is_array( $config ) ? $config : array(), $row['etag'] ? (string) $row['etag'] : null, $row['last_modified'] ? (string) $row['last_modified'] : null, is_array( $credentials ) ? $credentials : array() );
	}
}
