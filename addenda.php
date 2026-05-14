<?php
/**
 * Plugin Name: Addenda
 * Description: ==Highlights==, H2–H3 anchors, {{TOC}}, and [^inline footnotes].
 * Version: 1.0
 * Author: Jimmy Baum
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'the_content', 'adn_process_content', 20 );
add_filter( 'the_excerpt', 'adn_mark_syntax' );
add_filter( 'widget_text',  'adn_mark_syntax' );

function adn_process_content( $content ) {
	if ( is_admin() ) {
		return $content;
	}

	// ==highlight== → <mark> (works in paragraphs, lists, blockquotes, etc.)
	$content = adn_mark_syntax( $content );

	// [^footnote text] → numbered superscripts; collect footnote bodies
	$footnotes = [];
	$fn_n      = 0;
	$content   = preg_replace_callback(
		'#(<(?:pre|code)[^>]*>.*?</(?:pre|code)>)|\[\^([^\]]+)\]#is',
		function ( $m ) use ( &$footnotes, &$fn_n ) {
			if ( $m[1] !== '' ) {
				return $m[1];
			}
			$fn_n++;
			$footnotes[] = $m[2];
			return '<sup id="fnref-' . $fn_n . '"><a href="#fn-' . $fn_n . '">' . $fn_n . '</a></sup>';
		},
		$content
	);

	// Scan H2–H3 headings once to build shared slug list + TOC data
	$all_headings = adn_scan_headings( $content );

	// Stamp id="" onto H2–H3 that lack one, using the pre-built slug list
	$content = adn_add_heading_ids( $content, $all_headings );

	// {{TOC}}: render on front-end, strip from RSS feeds.
	// The fallback regex skips <code>/<pre> blocks so `{{TOC}}` in inline code
	// is left as literal text rather than expanded.
	if ( strpos( $content, '{{TOC}}' ) !== false ) {
		if ( is_feed() ) {
			$content = preg_replace( '#<p>\s*\{\{TOC\}\}\s*</p>#i', '', $content );
			$content = preg_replace_callback(
				'#(<(?:pre|code)[^>]*>.*?</(?:pre|code)>)|\{\{TOC\}\}#is',
				function ( $m ) {
					return $m[1] !== '' ? $m[1] : '';
				},
				$content
			);
		} else {
			$toc     = adn_build_toc( $all_headings );
			$content = preg_replace( '#<p>\s*\{\{TOC\}\}\s*</p>#i', $toc, $content );
			$content = preg_replace_callback(
				'#(<(?:pre|code)[^>]*>.*?</(?:pre|code)>)|\{\{TOC\}\}#is',
				function ( $m ) use ( $toc ) {
					return $m[1] !== '' ? $m[1] : $toc;
				},
				$content
			);
		}
	}

	// Append footnotes (no back-links in feeds)
	if ( $footnotes ) {
		$content .= adn_build_footnotes( $footnotes, is_feed() );
	}

	return $content;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function adn_mark_syntax( $content ) {
	return preg_replace_callback(
		'#(<(?:pre|code)[^>]*>.*?</(?:pre|code)>)|==(.+?)==#isu',
		function ( $m ) {
			return $m[1] !== '' ? $m[1] : '<mark>' . $m[2] . '</mark>';
		},
		$content
	);
}

/**
 * Returns a slug that doesn't collide with $used.
 * Caller is responsible for pushing the returned value into $used.
 */
function adn_unique_slug( $base, array $used ) {
	$slug = $base;
	$i    = 2;
	while ( in_array( $slug, $used, true ) ) {
		$slug = $base . '-' . $i++;
	}
	return $slug;
}

/**
 * First pass: walk all H2–H3 headings and resolve their final ids.
 * Headings that already have id="" keep theirs; others get a computed slug.
 * Returns array of [ 'level', 'text', 'slug' ] in document order.
 */
function adn_scan_headings( $content ) {
	preg_match_all( '#<h([23])([^>]*)>(.*?)</h\1>#is', $content, $matches, PREG_SET_ORDER );

	$used     = [];
	$headings = [];

	foreach ( $matches as $m ) {
		$attrs = $m[2];
		$inner = $m[3];
		$text  = wp_strip_all_tags( $inner );

		if ( preg_match( '/\bid=["\']([^"\']+)["\']/', $attrs, $id_m ) ) {
			// Already has an id — use it as-is
			$slug = $id_m[1];
		} else {
			$base = sanitize_title( $text ) ?: 'heading';
			$slug = adn_unique_slug( $base, $used );
		}

		$used[]     = $slug;
		$headings[] = [
			'level' => (int) $m[1],
			'text'  => $text,
			'slug'  => $slug,
		];
	}

	return $headings;
}

/**
 * Second pass: write id="" onto H2–H3 that lack one.
 * Pulls slugs from the array built by adn_scan_headings (same order = same slugs).
 */
function adn_add_heading_ids( $content, array $all_headings ) {
	$index = 0;

	return preg_replace_callback(
		'#<h([23])([^>]*)>(.*?)</h\1>#is',
		function ( $m ) use ( &$index, $all_headings ) {
			$level = $m[1];
			$attrs = $m[2];
			$inner = $m[3];
			$slug  = $all_headings[ $index ]['slug'];
			$index++;

			// Already has id — leave untouched
			if ( strpos( $attrs, 'id=' ) !== false ) {
				return $m[0];
			}

			return '<h' . $level . $attrs . ' id="' . esc_attr( $slug ) . '">' . $inner . '</h' . $level . '>';
		},
		$content
	);
}

/**
 * Builds a nested <nav><ul> TOC from the heading list.
 * Nesting mirrors heading level (H2 = top, H3 = nested under parent H2).
 */
function adn_build_toc( array $headings ) {
	if ( ! $headings ) {
		return '';
	}

	$html   = '<nav class="adn-toc" aria-label="Table of contents"><div class="adn-toc-label"><strong>Contents</strong></div>';
	$depth  = 0;
	$levels = [];

	foreach ( $headings as $h ) {
		$level = $h['level'];
		$link  = '<a href="#' . esc_attr( $h['slug'] ) . '">' . esc_html( $h['text'] ) . '</a>';

		if ( 0 === $depth ) {
			$html    .= '<ul><li>' . $link;
			$levels[] = $level;
			$depth    = 1;
		} elseif ( $level > end( $levels ) ) {
			// Deeper level — open a nested list
			$html    .= '<ul><li>' . $link;
			$levels[] = $level;
			$depth++;
		} elseif ( $level === end( $levels ) ) {
			// Same level — sibling item
			$html .= '</li><li>' . $link;
		} else {
			// Shallower — close nested list(s) until we're at the right level
			while ( count( $levels ) > 1 && end( $levels ) > $level ) {
				array_pop( $levels );
				$html .= '</li></ul>';
				$depth--;
			}
			$html .= '</li><li>' . $link;
			array_pop( $levels );
			$levels[] = $level;
		}
	}

	// Close all open tags
	while ( $depth > 0 ) {
		$html .= '</li></ul>';
		$depth--;
	}

	$html .= '</nav>';
	return $html;
}

/**
 * Builds the footnotes list appended at the end of post content.
 * $feed = true omits the ↩ back-links (RSS readers don't need anchor navigation).
 */
function adn_build_footnotes( array $footnotes, $feed = false ) {
	$html  = '<hr class="adn-footnotes-sep">';
	$html .= '<ol class="adn-footnotes">';

	foreach ( $footnotes as $i => $note ) {
		$n    = $i + 1;
		$back = $feed
			? ''
			: ' <a href="#fnref-' . $n . '" class="adn-fn-back" aria-label="Back to reference ' . $n . '">&#8617;</a>';
		$html .= '<li id="fn-' . $n . '">' . wp_kses_post( $note ) . $back . '</li>';
	}

	$html .= '</ol>';
	return $html;
}
