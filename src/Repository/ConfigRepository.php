<?php
declare(strict_types=1);

namespace WordPressFetch\Repository;

use WordPressFetch\Infrastructure\Database\Schema;

final class ConfigRepository {
	/** @return list<array<string,mixed>> */
	public function all( string $entity, string $type = '' ): array {
		global $wpdb;
		$table = Schema::table( $this->entity( $entity ) );
		$sql   = "SELECT * FROM {$table}";
		$args  = array();
		if ( 'profiles' === $entity && $type ) {
			$sql   .= ' WHERE type=%s';
			$args[] = sanitize_key( $type );
		} $sql .= ' ORDER BY name';
		$rows   = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query structure and table are internal allowlists.
		foreach ( $rows ? $rows : array() as &$row ) {
			$decoded       = json_decode( (string) $row['config'], true );
			$row['config'] = is_array( $decoded ) ? $decoded : array();
		} return $rows ? $rows : array();
	}

	/** @param array<string,mixed> $data */
	public function save( string $entity, array $data, int $id = 0 ): int {
		global $wpdb;
		$table = Schema::table( $this->entity( $entity ) );
		$now   = current_time( 'mysql', true );
		$row   = array(
			'name'       => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'config'     => wp_json_encode( is_array( $data['config'] ?? null ) ? $data['config'] : array() ),
			'updated_at' => $now,
		);
		if ( 'profiles' === $entity ) {
			$row['type'] = in_array( $data['type'] ?? '', array( 'mapping', 'seo', 'rules', 'media', 'schedule' ), true ) ? $data['type'] : 'mapping';
		} else {
			$parent_id        = max( 0, (int) ( $data['parent_id'] ?? 0 ) );
			$row['parent_id'] = $parent_id ? $parent_id : null;
		} if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) );
			return $id;
		} $row['created_at'] = $now;
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}
	public function delete( string $entity, int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( Schema::table( $this->entity( $entity ) ), array( 'id' => $id ), array( '%d' ) ); }
	private function entity( string $entity ): string {
		if ( ! in_array( $entity, array( 'groups', 'profiles' ), true ) ) {
			throw new \InvalidArgumentException( 'Unsupported configuration entity.' );
		} return $entity; }
}
