<?php
declare(strict_types=1);

namespace WordPressFetch\Import;

final class RuleEngine {
	/**
	 * @param array<string,mixed> $group Rule group.
	 * @param array<string,mixed> $item Normalized item.
	 */
	public function matches( array $group, array $item ): bool {
		$mode    = strtoupper( (string) ( $group['mode'] ?? 'ALL' ) );
		$results = array();
		foreach ( (array) ( $group['conditions'] ?? array() ) as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			} $results[] = isset( $condition['conditions'] ) ? $this->matches( $condition, $item ) : $this->evaluate( $condition, $item ); }
		return 'ANY' === $mode ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	/**
	 * @param array<string,mixed> $condition Condition.
	 * @param array<string,mixed> $item Normalized item.
	 */
	private function evaluate( array $condition, array $item ): bool {
		$field    = (string) ( $condition['field'] ?? '' );
		$operator = (string) ( $condition['operator'] ?? 'equals' );
		$expected = (string) ( $condition['value'] ?? '' );
		$actual   = $item[ $field ] ?? null;
		$text     = is_array( $actual ) ? implode( ', ', array_map( 'strval', $actual ) ) : (string) $actual;
		return match ( $operator ) {
			'equals' => $text === $expected, 'not_equals' => $text !== $expected, 'contains' => str_contains( $text, $expected ), 'not_contains' => ! str_contains( $text, $expected ),
			'starts_with' => str_starts_with( $text, $expected ), 'ends_with' => str_ends_with( $text, $expected ), 'greater_than' => (float) $text > (float) $expected, 'less_than' => (float) $text < (float) $expected,
			'exists' => null !== $actual && '' !== $text, 'not_exists' => null === $actual || '' === $text, 'in_list' => in_array( $text, array_map( 'trim', explode( ',', $expected ) ), true ), 'not_in_list' => ! in_array( $text, array_map( 'trim', explode( ',', $expected ) ), true ),
			'matches_regex', 'not_matches_regex' => $this->regex( $text, $expected, 'not_matches_regex' === $operator ), default => false,
		};
	}

	private function regex( string $actual, string $pattern, bool $negate ): bool {
		if ( strlen( $pattern ) > 500 ) {
			return false;
		} set_error_handler( static fn(): bool => true );
		$result = preg_match( $pattern, $actual );
		restore_error_handler();
		return $negate ? 0 === $result : 1 === $result; }
}
