<?php
declare(strict_types=1);

namespace WordPressFetch\Export;

use WordPressFetch\Repository\ConfigRepository;
use WordPressFetch\Repository\SourceRepository;

final class Configuration {
	public function __construct( private readonly SourceRepository $sources, private readonly ConfigRepository $configs ) {}
	/** @return array<string,mixed> */ public function export(): array {
		return array(
			'schema_version'       => '1.0',
			'exported_at'          => gmdate( DATE_ATOM ),
			'credentials_included' => false,
			'settings'             => get_option( 'wpfetch_settings', array() ),
			'groups'               => $this->configs->all( 'groups' ),
			'profiles'             => $this->configs->all( 'profiles' ),
			'sources'              => $this->sources->exportAll(),
		); }
	/**
	 * @param array<string,mixed> $data Configuration.
	 * @return list<string>
	 */
	public function validate( array $data ): array {
		$errors = array();
		if ( '1.0' !== ( $data['schema_version'] ?? '' ) ) {
			$errors[] = __( 'Unsupported configuration schema version.', 'wordpress-fetch' );
		} if ( ! isset( $data['sources'] ) || ! is_array( $data['sources'] ) ) {
			$errors[] = __( 'The configuration has no source list.', 'wordpress-fetch' );
		} if ( count( (array) ( $data['sources'] ?? array() ) ) > 1000 ) {
			$errors[] = __( 'A single import is limited to 1,000 sources.', 'wordpress-fetch' );
		} return $errors; }
}
