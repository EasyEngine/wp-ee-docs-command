<?php
/**
 * File to modify handbook posts.
 *
 * @package ee-markdown-importer
 */

namespace WPOrg_Cli;

/**
 * Class to modify handbook post.
 */
class Handbook {

	/**
	 * Append a "Edit on GitHub" link to Handbook document titles
	 *
	 * @param string $title The title of edit link.
	 * @param int    $id    Post ID.
	 *
	 * @return string
	 */
	public static function filter_the_title_edit_link( $title, $id = null ) {

		// Only apply to the main title for the document.
		if ( ! is_singular( 'handbook' )
			|| ! is_main_query()
			|| ! in_the_loop()
			|| get_queried_object_id() !== $id ) {
			return $title;
		}

		$markdown_source = self::get_markdown_edit_link( get_the_ID() );
		if ( ! $markdown_source ) {
			return $title;
		}

		return sprintf(
			'%s <a class="github-edit" href="%s"><img src="%s"> <span>%s</span></a>',
			$title,
			esc_url( $markdown_source ),
			esc_url( plugins_url( '/images/github-mark.png', __DIR__ ) ),
			esc_html__( 'Edit', 'ee-markdown-importer' )
		);

	}

	/**
	 *  WP-CLI Handbook pages are maintained in the GitHub repo, so the edit
	 * link should ridirect to there.
	 *
	 * @param string $link    Git hub link.
	 * @param int    $post_id Post ID.
	 * @param string $context Context using to set link.
	 *
	 * @return string
	 */
	public static function redirect_edit_link_to_github( $link, $post_id, $context ) {

		if ( is_admin() ) {
			return $link;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $link;
		}

		if ( 'handbook' !== $post->post_type ) {
			return $link;
		}

		$markdown_source = self::get_markdown_edit_link( $post_id );
		if ( ! $markdown_source ) {
			return $link;
		}

		if ( 'display' === $context ) {
			$markdown_source = esc_url( $markdown_source );
		}

		return $markdown_source;

	}

	/**
	 * O2 does inline editing, so we also need to remove the class name that it looks for.
	 *
	 * O2 obeys the edit_post capability for displaying the edit link, so we also need to manually
	 * add the edit link if it isn't there - it always redirects to GitHub, so it doesn't need to
	 * obey the edit_post capability in this instance.
	 *
	 * @param array $actions For which to add edit link.
	 * @param int   $post_id Post ID.
	 *
	 * @return array
	 */
	public static function redirect_o2_edit_link_to_github( $actions, $post_id ) {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $actions;
		}

		if ( 'handbook' !== $post->post_type ) {
			return $actions;
		}

		$markdown_source = self::get_markdown_edit_link( $post_id );
		if ( ! $markdown_source ) {
			return $actions;
		}

		/*
		 * Define our own edit post action for o2.
		 *
		 * Notable differences from the original are:
		 * - the 'href' parameter always goes to the GitHub source.
		 * - the 'o2-edit' class is missing, so inline editing is disabled.
		 */
		$edit_action = [
			'action'       => 'edit',
			'href'         => $markdown_source,
			'classes'      => [ 'edit-post-link' ],
			'rel'          => $post_id,
			'initialState' => 'default',
		];

		// Find and replace the existing edit action.
		$replaced = false;
		foreach ( $actions as &$action ) {
			if ( 'edit' === $action['action'] ) {
				$action   = $edit_action;
				$replaced = true;
				break;
			}
		}
		unset( $action );

		// If there was no edit action replaced, add it in manually.
		if ( ! $replaced ) {
			$actions[30] = $edit_action;
		}

		return $actions;
	}

	/**
	 * Function to get markdown edit link.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private static function get_markdown_edit_link( $post_id ) {

		$markdown_source = Markdown_Import::get_markdown_source( $post_id );

		if ( is_wp_error( $markdown_source ) ) {
			return '';
		}

		if ( 'github.com' !== wp_parse_url( $markdown_source, PHP_URL_HOST )
			|| false !== stripos( $markdown_source, '/edit/master/' ) ) {
			return $markdown_source;
		}

		$markdown_source = str_replace( '/blob/master/', '/edit/master/', $markdown_source );

		return $markdown_source;

	}

	/**
	 * Using DOMDocument, parse the content and add anchors to headers (H1-H6)
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The content.
	 *
	 * @return string         The content, updated if the content has H1-H6
	 */
	public static function add_the_anchor_links( $content ) {

		if ( ! is_singular( [ 'commands', 'handbook', 'post' ] ) || '' === $content ) {
			return $content;
		}

		$anchors = [];
		$doc     = new \DOMDocument();

		// START LibXML error management.
		// Modify state.
		$libxml_previous_state = libxml_use_internal_errors( true );
		$doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

		// Handle errors.
		libxml_clear_errors();

		// Restore.
		libxml_use_internal_errors( $libxml_previous_state );

		// END LibXML error management.
		foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $h ) {

			$headings = $doc->getElementsByTagName( $h );

			foreach ( $headings as $heading ) {

				$a       = $doc->createElement( 'a' );
				$newnode = $heading->appendChild( $a );

				$newnode->setAttribute( 'class', 'anchorlink dashicons-before' );

				// @codingStandardsIgnoreStart
				// $heading->nodeValue is from an external libray. Ignore the standard check sinice it doesn't fit the WordPress-Core standard
				$node_value = preg_replace( "/[^A-Za-z0-9\- ]/", '', $heading->nodeValue );
				$slug = $tmpslug = sanitize_title( $node_value );
				// @codingStandardsIgnoreEnd

				$i = 2;
				while ( false !== in_array( $slug, $anchors, true ) ) {
					$slug = sprintf( '%s-%d', $tmpslug, $i ++ );
				}

				$anchors[] = $slug;

				$heading->setAttribute( 'id', $slug );
				$newnode->setAttribute( 'href', '#' . $slug );
			}
		}

		return $doc->saveHTML();
	}

	/**
	 * Enable dashicons on the front-end.
	 * Load style.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function add_the_anchor_styles() {
		if ( is_singular() ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style(
				'anchored-header',
				EE_ANCHOR_CSS . '/anchored-header.css',
				[ 'dashicons' ],
				filemtime( EE_MARKDOWN_PLUGIN_DIR . '/css/anchored-header.css' )
			);
		}
	}

}
