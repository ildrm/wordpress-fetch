<?php
declare(strict_types=1);

namespace WordPressFetch\Cli;

final class ImportCommand {
	/**
	 * Queue and run an import for a source.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function run( array $args ): void {
		$id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( ! Commands::sources()->find( $id ) ) {
			\WP_CLI::error( 'Source not found.' ); }
		$job    = Commands::queue()->enqueue( 'fetch', array(), $id, 1 );
		$result = Commands::worker()->run( 1 );
		if ( $result['failed'] ) {
			\WP_CLI::error( 'Import job failed: ' . $job ); }
		\WP_CLI::success( 'Import job completed: ' . $job );
	}
}
