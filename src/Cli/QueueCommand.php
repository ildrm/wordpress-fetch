<?php
declare(strict_types=1);

namespace WordPressFetch\Cli;

final class QueueCommand {
	/**
	 * @param list<string>        $args Positional arguments.
	 * @param array<string,mixed> $assoc Associated arguments.
	 */
	public function run( array $args, array $assoc ): void {
		$limit = isset( $assoc['limit'] ) ? absint( $assoc['limit'] ) : 25;
		\WP_CLI::line( wp_json_encode( Commands::worker()->run( $limit ), JSON_PRETTY_PRINT ) ); }
	/** Show queue counts. */ public function failed(): void {
		\WP_CLI::line( wp_json_encode( Commands::queue()->counts(), JSON_PRETTY_PRINT ) ); }
	/** @param list<string> $args Positional arguments. */ public function retry( array $args ): void {
		$ok = Commands::queue()->retry( absint( $args[0] ?? 0 ) );
		if ( $ok ) {
			\WP_CLI::success( 'Job queued.' ); }
		\WP_CLI::error( 'Job could not be retried.' ); }
}
