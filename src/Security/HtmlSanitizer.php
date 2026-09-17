<?php
declare(strict_types=1);

namespace WordPressFetch\Security;

final class HtmlSanitizer {
	public function sanitize( string $html ): string {
		$html = preg_replace( '#<(script|style|object|embed|svg|math)[^>]*>.*?</\\1>#is', '', $html ) ?? '';
		$html = preg_replace( '#<(iframe)[^>]*>.*?</\\1>#is', '', $html ) ?? '';
		return wp_kses_post( $html );
	}
}
