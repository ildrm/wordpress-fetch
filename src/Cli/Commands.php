<?php
declare(strict_types=1);

namespace WordPressFetch\Cli;

use WordPressFetch\Queue\Queue;
use WordPressFetch\Queue\Worker;
use WordPressFetch\Repository\SourceRepository;

final class Commands {
	private static SourceRepository $sources;
	private static Queue $queue;
	private static Worker $worker;
	public static function register( SourceRepository $sources, Queue $queue, Worker $worker ): void {
		self::$sources = $sources;
		self::$queue   = $queue;
		self::$worker  = $worker;
		\WP_CLI::add_command( 'feed-syndicator source', SourceCommand::class );
		\WP_CLI::add_command( 'feed-syndicator queue', QueueCommand::class );
		\WP_CLI::add_command( 'feed-syndicator import', ImportCommand::class );
		\WP_CLI::add_command( 'feed-syndicator health', array( self::class, 'health' ) );
		\WP_CLI::add_command( 'feed-syndicator stats', array( self::class, 'health' ) ); }
	public static function sources(): SourceRepository {
		return self::$sources;
	} public static function queue(): Queue {
		return self::$queue;
	} public static function worker(): Worker {
		return self::$worker; }
	public static function health(): void {
		\WP_CLI::line(
			wp_json_encode(
				array(
					'queue'     => self::$queue->counts(),
					'cron_next' => wp_next_scheduled( \WordPressFetch\Scheduling\Scheduler::HOOK ),
					'schema'    => get_option( 'wpfetch_schema_version' ),
				),
				JSON_PRETTY_PRINT
			)
		); }
}
