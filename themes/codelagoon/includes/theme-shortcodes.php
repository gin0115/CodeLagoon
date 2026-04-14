<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Simple shortcode to output a string.
 *
 * @return string
 */
function codelag_sc_translatable_string(): string {
	return __( 'This string can be translated!', 'codelagoon' );
}
add_shortcode( 'translate-string', 'codelag_sc_translatable_string' );

/**
 * Icon-only login/logout link for the site header.
 *
 * Renders a Material Symbol wrapped in an <a> pointing at wp_logout_url() for
 * logged-in users, or wp_login_url() for guests. The label exists only for
 * screen readers; the icon itself carries the affordance.
 *
 * @return string
 */
function codelag_sc_loginout_icon(): string {
	if ( is_user_logged_in() ) {
		$href  = wp_logout_url( (string) ( home_url( add_query_arg( null, null ) ) ) );
		$icon  = 'logout';
		$label = __( 'Log out', 'codelagoon' );
	} else {
		$href  = wp_login_url( (string) ( home_url( add_query_arg( null, null ) ) ) );
		$icon  = 'login';
		$label = __( 'Log in', 'codelagoon' );
	}

	return sprintf(
		'<a class="codelag-site-header__icon-button" href="%1$s" aria-label="%2$s" title="%2$s">'
			. '<span class="material-symbols-outlined" aria-hidden="true">%3$s</span>'
			. '</a>',
		esc_url( $href ),
		esc_attr( $label ),
		esc_html( $icon )
	);
}
add_shortcode( 'codelag_loginout_icon', 'codelag_sc_loginout_icon' );

/**
 * Icon-only "Add new lagoon" link for the site header.
 *
 * Always visible. Links to wp-admin's post-new screen for the `lagoon` CPT;
 * WordPress auto-redirects unauthenticated visitors to the login screen with
 * a `redirect_to` param so they come back to post-new after logging in.
 *
 * @return string
 */
function codelag_sc_add_new_lagoon_icon(): string {
	$href  = admin_url( 'post-new.php?post_type=lagoon' );
	$label = __( 'Add new lagoon', 'codelagoon' );

	return sprintf(
		'<a class="codelag-site-header__icon-button" href="%1$s" aria-label="%2$s" title="%2$s">'
			. '<span class="material-symbols-outlined" aria-hidden="true">add</span>'
			. '</a>',
		esc_url( $href ),
		esc_attr( $label )
	);
}
add_shortcode( 'codelag_add_new_lagoon_icon', 'codelag_sc_add_new_lagoon_icon' );

/**
 * Icon-only "Edit profile" link for the site header.
 *
 * Only renders for logged-in users. Guests get an empty string (they already
 * have the login icon for account flows).
 *
 * @return string
 */
function codelag_sc_edit_profile_icon(): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$href  = (string) get_edit_profile_url();
	$label = __( 'Edit profile', 'codelagoon' );

	return sprintf(
		'<a class="codelag-site-header__icon-button" href="%1$s" aria-label="%2$s" title="%2$s">'
			. '<span class="material-symbols-outlined" aria-hidden="true">person</span>'
			. '</a>',
		esc_url( $href ),
		esc_attr( $label )
	);
}
add_shortcode( 'codelag_edit_profile_icon', 'codelag_sc_edit_profile_icon' );

/**
 * Site-header search icon. Icon + hidden input in one go, with a tiny
 * inline script that shows the input on click and hides it on the close
 * button. Submits `?search=<term>` to the lagoon CPT archive.
 *
 * @return string
 */
function codelag_sc_search_icon(): string {
	$href = (string) get_post_type_archive_link( 'lagoon' );
	if ( '' === $href ) {
		$href = home_url( '/' );
	}
	$label = __( 'Search', 'codelagoon' );

	// Prefill from the URL when the archive was loaded with ?search=term —
	// also auto-reveals the form so the user sees their active search.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill.
	$current_search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '';
	$is_open        = '' !== $current_search;

	$wrap_style  = 'display:inline-flex;align-items:center;';
	$form_base   = 'margin:0;display:inline-flex;align-items:center;margin-right:0.35rem;'
		. 'transition:opacity 200ms ease, visibility 0s linear 200ms;';
	$form_closed = 'opacity:0;visibility:hidden;pointer-events:none;';
	$form_open   = 'opacity:1;visibility:visible;pointer-events:auto;';
	$form_style  = $form_base . ( $is_open ? $form_open : $form_closed );
	$input_style = 'background:var(--cl-surface-high);color:var(--cl-on-surface);border:1px solid var(--cl-outline-variant);border-radius:var(--cl-radius-pill);padding:0.35rem 0.85rem;font:inherit;font-size:0.82rem;width:14rem;outline:none;';

	$toggle_js = "var f=document.getElementById('codelag-search-form');var o=f.getAttribute('data-open')!=='true';f.setAttribute('data-open',o?'true':'false');f.style.opacity=o?'1':'0';f.style.visibility=o?'visible':'hidden';f.style.pointerEvents=o?'auto':'none';if(o)f.querySelector('input').focus();return false;";

	return sprintf(
		'<span style="%4$s">'
			. '<form id="codelag-search-form" role="search" method="get" action="%2$s" style="%5$s" data-open="%8$s">'
			. '<input type="search" name="search" aria-label="%1$s" value="%7$s" style="%6$s" />'
			. '</form>'
			. '<a class="codelag-site-header__icon-button" href="#" id="codelag-search-toggle" aria-label="%1$s" title="%1$s" onclick="%9$s">'
			. '<span class="material-symbols-outlined" aria-hidden="true">search</span>'
			. '</a>'
			. '</span>',
		esc_attr( $label ),             // %1$s
		esc_url( $href ),               // %2$s
		esc_attr( $wrap_style ),        // %3$s — placeholder retained
		esc_attr( $wrap_style ),        // %4$s wrap
		esc_attr( $form_style ),        // %5$s form
		esc_attr( $input_style ),       // %6$s input
		esc_attr( $current_search ),    // %7$s prefilled value
		$is_open ? 'true' : 'false',    // %8$s data-open
		esc_attr( $toggle_js )          // %9$s onclick
	);
}
add_shortcode( 'codelag_search_icon', 'codelag_sc_search_icon' );
