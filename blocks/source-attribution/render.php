<?php
/** Dynamic source attribution block. */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
$wpfetch_url = get_post_meta( get_the_ID(), '_wpfetch_original_url', true );
if ( $wpfetch_url ) : ?>
<p <?php echo get_block_wrapper_attributes( array( 'class' => 'wpfetch-attribution' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><a href="<?php echo esc_url( $wpfetch_url ); ?>" rel="noopener noreferrer"><?php esc_html_e( 'Read the original article', 'wordpress-fetch' ); ?></a></p>
<?php endif; ?>
