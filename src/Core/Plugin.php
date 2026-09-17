<?php
declare(strict_types=1);

namespace WordPressFetch\Core;

use WordPressFetch\Admin\Admin;
use WordPressFetch\Api\Controller;
use WordPressFetch\Cli\Commands;
use WordPressFetch\Editor\Integration;
use WordPressFetch\Export\Configuration;
use WordPressFetch\Feed\Discovery;
use WordPressFetch\Feed\HttpClient;
use WordPressFetch\Feed\Opml;
use WordPressFetch\Feed\Parser;
use WordPressFetch\Import\Importer;
use WordPressFetch\Import\MappingEngine;
use WordPressFetch\Import\MediaHandler;
use WordPressFetch\Import\RuleEngine;
use WordPressFetch\Import\TemplateEngine;
use WordPressFetch\Import\UrlNormalizer;
use WordPressFetch\Logging\Logger;
use WordPressFetch\Notifications\Notifier;
use WordPressFetch\Queue\Queue;
use WordPressFetch\Queue\Worker;
use WordPressFetch\Repository\SourceRepository;
use WordPressFetch\Repository\ConfigRepository;
use WordPressFetch\Scheduling\Scheduler;
use WordPressFetch\Security\HtmlSanitizer;
use WordPressFetch\Security\SecretVault;
use WordPressFetch\Security\UrlGuard;
use WordPressFetch\Seo\Manager;

final class Plugin {
	private static ?self $instance = null;
	private bool $booted           = false;
	private Worker $worker;
	private Queue $queue;
	private SourceRepository $sources;
	private Controller $api;
	private Manager $seo;

	public static function instance(): self {
		return self::$instance ??= new self(); }
	private function __construct() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		} $this->booted = true;
		$guard          = new UrlGuard();
		$vault          = new SecretVault();
		$logger         = new Logger();
		$notifier       = new Notifier();
		$http           = new HttpClient( $guard );
		$parser         = new Parser( new HtmlSanitizer() );
		$this->sources  = new SourceRepository( $vault );
		$this->queue    = new Queue();
		$this->seo      = new Manager();
		$importer       = new Importer( new UrlNormalizer(), new MappingEngine( new TemplateEngine() ), new RuleEngine(), new MediaHandler( $guard ), $logger );
		$this->worker   = new Worker( $this->queue, $this->sources, $http, $parser, $importer, $logger, $notifier );
		$this->api      = new Controller( $this->sources, new ConfigRepository(), $this->queue, $http, $parser, new Discovery( $http, $guard ), new Opml( $guard ), $guard, $this->seo );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this->api, 'register_routes' ) );
		add_filter( 'cron_schedules', array( Scheduler::class, 'intervals' ) );
		$scheduler = new Scheduler( $this->queue );
		add_action( Scheduler::HOOK, array( $scheduler, 'tick' ) );
		add_action( 'wpfetch_run_worker', array( $this, 'runWorker' ) );
		add_action(
			'wpfetch_daily_maintenance',
			static function () use ( $logger ): void {
				$logger->prune();
			}
		);
		$this->seo->hooks();
		( new Integration() )->hooks();
		if ( is_admin() ) {
			( new Admin( $this->seo ) )->hooks();
			add_action( 'admin_notices', array( $notifier, 'renderAdminNotices' ) ); }
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register( $this->sources, $this->queue, $this->worker ); }
	}

	public function init(): void {
		load_plugin_textdomain( 'wordpress-fetch', false, dirname( plugin_basename( WPFETCH_FILE ) ) . '/languages' );
		if ( get_option( 'wpfetch_schema_version' ) !== WPFETCH_SCHEMA_VERSION ) {
			\WordPressFetch\Infrastructure\Database\Schema::migrate(); }
		register_block_type( WPFETCH_DIR . 'blocks/source-attribution' );
	}

	public function runWorker(): void {
		$settings = wp_parse_args( get_option( 'wpfetch_settings', array() ), Activator::defaults() );
		$this->worker->run( (int) $settings['queue_batch_size'] ); }
}
