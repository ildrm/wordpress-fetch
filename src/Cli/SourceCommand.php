<?php
declare(strict_types=1);

namespace WordPressFetch\Cli;

final class SourceCommand {
	/** List configured sources. @subcommand list */ public function list_(): void {
		$data = Commands::sources()->page( 1, 100 );
		\WP_CLI\Utils\format_items( 'table', $data['items'], array( 'id', 'name', 'status', 'health', 'schedule', 'last_success' ) ); }
	/** @param list<string> $args Positional arguments. */ public function fetch( array $args ): void {
		$id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( ! Commands::sources()->find( $id ) ) {
			\WP_CLI::error( 'Source not found.' );
		} $job = Commands::queue()->enqueue( 'fetch', array(), $id, 1 );
		\WP_CLI::success( 'Queued job ' . $job ); }
	/** @param list<string> $args Positional arguments. */ public function test( array $args ): void {
		$id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( ! Commands::sources()->find( $id ) ) {
			\WP_CLI::error( 'Source not found.' ); }
		Commands::queue()->enqueue( 'dry_run', array(), $id, 1 );
		$result = Commands::worker()->run( 1 );
		if ( $result['failed'] ) {
			\WP_CLI::error( 'Source test failed.' ); }
		\WP_CLI::success( wp_json_encode( $result ) ); }
}
