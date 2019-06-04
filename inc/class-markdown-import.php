<?php
/**
 * File to import markdown.
 *
 * @package ee-markdown-importer
 */

namespace WPOrg_Cli;

use WP_Error;
use WP_Query;

/**
 * Class to import markdown.
 */
class Markdown_Import {

	/**
	 * Variable to store command manifest file path.
	 *
	 * @var string
	 */
	private static $command_manifest = EE_DOC_OUTPUT_DIR . '/bin/commands-manifest.json';

	/**
	 * Variable to store input name.
	 *
	 * @var string
	 */
	private static $input_name = 'wporg-cli-markdown-source';

	/**
	 * Variable to store meta key name.
	 *
	 * @var string
	 */
	private static $meta_key = 'wporg_cli_markdown_source';

	/**
	 * Variable to store nonce field name.
	 *
	 * @var string
	 */
	private static $nonce_name = 'wporg-cli-markdown-source-nonce';

	/**
	 * Variable to store submit button name.
	 *
	 * @var string
	 */
	private static $submit_name = 'wporg-cli-markdown-import';

	/**
	 * Register our cron task if it doesn't already exist
	 *
	 * @return void
	 */
	public static function action_init() {

	}

	/**
	 * Variable to store supported post type array.
	 *
	 * @var array
	 */
	private static $supported_post_types = [ 'commands' ];

	/**
	 * Variable to store post per page value.
	 *
	 * @var string
	 */
	private static $posts_per_page = 100;

	/**
	 * Function to handle manifest import functionality.
	 *
	 * @return mixed
	 */
	public static function action_wporg_cli_manifest_import() {

		if ( ! defined( 'WP_CLI' ) || empty( WP_CLI ) ) {
			return new WP_Error( 'wp-cli-only', 'Function should be run from wp cli only.' );
		}

		\WP_CLI::run_command( [ 'ee', 'gen-docs' ] );

		$response = file_get_contents( self::$command_manifest );
		if ( empty( $response ) ) {
			return new WP_Error( 'empty-json', 'Markdown source not found.' );
		}

		$manifest = json_decode( $response, true );

		if ( ! $manifest ) {
			return new WP_Error( 'invalid-manifest', 'Manifest did not unfurl properly.' );
		}

		// Fetch all command posts for comparison.
		$q        = new WP_Query(
			[
				'post_type'      => self::$supported_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => self::$posts_per_page,
			]
		);
		$existing = $q->posts;
		$created  = 0;

		// TODO: Custom change in generated doc. Need to fix it in data/markdown generation.
		foreach ( $manifest as $key => $doc ) {
			if ( 'ee site reload --type=html' === $doc['title'] ) {
				$manifest[ $key ]['title'] = 'ee site reload';
			}

			if ( 'ee site restart --type=html' === $doc['title'] ) {
				$manifest[ $key ]['title'] = 'ee site restart';
			}

			if ( 'ee site create --type=html' === $doc['title'] ) {
				$manifest[ $key ]['title'] = 'ee site create';
			}
		}

		foreach ( $manifest as $doc ) {

			// Already exists.
			if ( wp_filter_object_list( $existing, [ 'post_title' => sanitize_text_field( wp_slash( $doc['title'] ) ) ] ) ) {
				continue;
			}
			$post_parent = null;
			if ( ! empty( $doc['parent'] ) ) {
				// Find the parent in the existing set.
				$parents = wp_filter_object_list( $existing, [ 'post_title' => sanitize_text_field( wp_slash( $manifest[ $doc['parent'] ]['title'] ) ) ] );
				if ( ! empty( $parents ) ) {
					$parent = array_shift( $parents );
				} else {
					// Create the parent and add it to the stack.
					if ( isset( $manifest[ $doc['parent'] ] ) ) {
						$parent_doc = $manifest[ $doc['parent'] ];
						$parent     = self::create_post_from_manifest_doc( $parent_doc );
						if ( $parent ) {
							$created++;
							$existing[] = $parent;
						} else {
							continue;
						}
					} else {
						continue;
					}
				}
				$post_parent = $parent->ID;
			}
			$post = self::create_post_from_manifest_doc( $doc, $post_parent );
			if ( $post ) {
				$created++;
				$existing[] = $post;
			}
		}
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::success( sprintf( 'Successfully created %s command pages.', $created ) );
		}

		// Run markdown importer after creating successful posts.
		apply_filters( 'wporg_cli_markdown_import', 'action_wporg_cli_markdown_import' );
		// @todo: remove this after code enhancement.
		apply_filters( 'wporg_cli_hb_all_import', [ 'WPOrg_Cli\Markdown_Hb_Import', 'action_wporg_cli_hb_manifest_import' ] );
	}

	/**
	 * Create a new command page from the manifest document
	 *
	 * @param array $doc         Command document data.
	 * @param int   $post_parent Parent post ID.
	 *
	 * @return mixed
	 */
	private static function create_post_from_manifest_doc( $doc, $post_parent = null ) {
		$post_data = [
			'post_type'   => 'commands',
			'post_status' => 'publish',
			'post_parent' => $post_parent,
			'post_title'  => sanitize_text_field( wp_slash( $doc['title'] ) ),
			'post_name'   => sanitize_title_with_dashes( $doc['slug'] ),
		];
		$post_id   = wp_insert_post( $post_data );

		if ( ! $post_id ) {
			return false;
		}

		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::log( "Created post {$post_id} for {$doc['title']}." );
		}

		update_post_meta( $post_id, self::$meta_key, esc_url_raw( $doc['markdown_source'] ) );

		return get_post( $post_id );

	}

	/**
	 * Function to import markdown.
	 *
	 * @return void
	 */
	public static function action_wporg_cli_markdown_import() {
		$q       = new WP_Query(
			[
				'post_type'      => self::$supported_post_types,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => self::$posts_per_page,
			]
		);
		$ids     = $q->posts;
		$success = 0;

		foreach ( $ids as $id ) {

			$ret = self::update_post_from_markdown_source( $id );

			if ( class_exists( 'WP_CLI' ) ) {

				if ( is_wp_error( $ret ) ) {
					\WP_CLI::warning( $ret->get_error_message() );
				} else {
					// translators: %d: post id.
					\WP_CLI::log( sprintf( esc_html__( 'Updated %d from markdown source', 'ee-markdown-importer' ), $id ) );
					$success++;
				}
			}
		}

		if ( class_exists( 'WP_CLI' ) ) {
			$total = count( $ids );
			// translators: %1$s: Success count, %2$s: Total count.
			\WP_CLI::success( sprintf( esc_html__( 'Successfully updated %1$s of %2$s command pages.', 'ee-markdown-importer' ), $success, $total ) );
		}

	}

	/**
	 * Handle a request to import from the markdown source.
	 *
	 * @return void
	 */
	public static function action_load_post_php() {

		$submit_btn_name  = filter_input( INPUT_GET, self::$submit_name, FILTER_SANITIZE_STRING );
		$nonce_field_name = filter_input( INPUT_GET, self::$nonce_name, FILTER_SANITIZE_STRING );
		$post_id          = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

		if ( empty( $submit_btn_name )
			|| empty( $nonce_field_name )
			|| empty( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;
		if ( ! current_user_can( 'edit_post', $post_id )
			|| ! wp_verify_nonce( $nonce_field_name, self::$input_name )
			|| ! in_array( get_post_type( $post_id ), self::$supported_post_types, true ) ) {
			return;
		}
		$response = self::update_post_from_markdown_source( $post_id );
		if ( is_wp_error( $response ) ) {
			wp_die( esc_html( $response->get_error_message() ) );
		}
		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * Add an input field for specifying Markdown source.
	 *
	 * @param object $post Post Object.
	 *
	 * @return void
	 */
	public static function action_edit_form_after_title( $post ) {

		if ( ! in_array( $post->post_type, self::$supported_post_types, true ) ) {
			return;
		}

		$markdown_source = get_post_meta( $post->ID, self::$meta_key, true );
		?>
		<label>Markdown source: <input
					type="text"
					name="<?php echo esc_attr( self::$input_name ); ?>"
					value="<?php echo esc_attr( $markdown_source ); ?>"
					placeholder="<?php esc_attr_e( 'Enter a URL representing a markdown file to import', 'ee-markdown-importer' ); ?>"
					size="50" />
		</label>
		<?php
		if ( $markdown_source ) :
			$update_link = add_query_arg(
				[
					self::$submit_name => 'import',
					self::$nonce_name  => wp_create_nonce( self::$input_name ),
				],
				get_edit_post_link( $post->ID, 'raw' )
			);
			?>
			<a class="button button-small button-primary" href="<?php echo esc_url( $update_link ); ?>"><?php esc_html_e( 'Import', 'ee-markdown-importer' ); ?></a>
		<?php endif; ?>
		<?php wp_nonce_field( self::$input_name, self::$nonce_name ); ?>
		<?php
	}

	/**
	 * Save the Markdown source input field
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function action_save_post( $post_id ) {

		$markdown_input_name = filter_input( INPUT_POST, self::$input_name, FILTER_SANITIZE_URL );
		$markdown_nonce_name = filter_input( INPUT_POST, self::$nonce_name, FILTER_SANITIZE_STRING );

		if ( ! isset( $markdown_input_name )
			|| ! isset( $markdown_nonce_name )
			|| ! in_array( get_post_type( $post_id ), self::$supported_post_types, true ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $markdown_nonce_name, self::$input_name ) ) {
			return;
		}

		$markdown_source = '';
		if ( ! empty( $markdown_input_name ) ) {
			$markdown_source = esc_url_raw( $markdown_input_name );
		}

		update_post_meta( $post_id, self::$meta_key, $markdown_source );
	}

	/**
	 * Filter cron schedules to add a 15 minute schedule
	 *
	 * @param array $schedules Cron details.
	 *
	 * @return array
	 */
	public static function filter_cron_schedules( $schedules ) {

		$schedules['15_minutes'] = [
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => '15 minutes',
		];
		return $schedules;
	}

	/**
	 * Update a post from its Markdown source
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return mixed
	 */
	private static function update_post_from_markdown_source( $post_id ) {
		$markdown_source = self::get_markdown_source( $post_id );

		if ( is_wp_error( $markdown_source ) ) {
			return $markdown_source;
		}
		if ( ! function_exists( 'jetpack_require_lib' ) ) {
			return new WP_Error( 'missing-jetpack-require-lib', 'jetpack_require_lib() is missing on system.' );
		}

		// Moved to local directory markdown file location.
		$markdown_source = preg_replace( '#https?://github\.com/([^/]+/[^/]+)/blob/master/(.+)#', '$2', $markdown_source );

		// Transform GitHub repo HTML pages into their raw equivalents.
		$response = file_exists( $markdown_source ) ? file_get_contents( $markdown_source ) : '';
		if ( empty( $response ) ) {
			return new WP_Error( 'empty-file', 'Markdown source is empty. File: ' . $markdown_source );
		}
		$markdown = $response;

		// Strip YAML doc from the header.
		$markdown = preg_replace( '#^---(.+)---#Us', '', $markdown );
		$title    = null;
		if ( preg_match( '/^#\s(.+)/', $markdown, $matches ) ) {
			$title    = $matches[1];
			$markdown = preg_replace( '/^#\s(.+)/', '', $markdown );
		}

		// Transform to HTML and save the post.
		$parser = new \Parsedown();
		$html   = $parser->text( $markdown );

		// TODO: Custom change in generated doc. Need to fix it in data/markdown generation.
		$html = str_replace( '<h1>', '<h2>', $html );
		$html = str_replace( '</h1>', '</h2>', $html );

		$prepend_text = '';
		$post_excerpt = '';

		// TODO: Custom change in generated doc. Need to fix it in data/markdown generation.
		if ( 'ee site create --type=html' === $title || 'ee site create' === $title ) {
			$prepend_text = '<h2>ee site create -–type=html</h2>';
			$post_excerpt = 'Runs site installation with provided site type.';
			$title        = 'ee site create';
		}

		// TODO: Custom change in generated doc. Need to fix it in data/markdown generation.
		if ( 'ee site restart --type=html' === $title || 'ee site restart' === $title ) {
			$temp_html = explode( '<h2>ee site restart --type=wp</h2>', $html );
			$html      = $temp_html[1];
			$title     = 'ee site restart';
		}

		// TODO: Custom change in generated doc. Need to fix it in data/markdown generation.
		if ( 'ee site reload --type=html' === $title || 'ee site reload' === $title ) {
			$temp_html = explode( '<h2>ee site reload --type=wp</h2>', $html );
			$html      = $temp_html[1];
			$title     = 'ee site reload';
		}

		if ( ! empty( $prepend_text ) ) {
			$temp_html = $html;
			$html      = $prepend_text . $temp_html;
		}

		$post_data = [
			'ID'           => $post_id,
			'post_content' => wp_filter_post_kses( wp_slash( $html ) ),
			'post_excerpt' => $post_excerpt,
		];
		if ( ! is_null( $title ) ) {
			$post_data['post_title'] = sanitize_text_field( wp_slash( $title ) );
		}
		wp_update_post( $post_data );
		return true;
	}

	/**
	 * Retrieve the markdown source URL for a given post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return mixed
	 */
	public static function get_markdown_source( $post_id ) {
		$markdown_source = get_post_meta( $post_id, self::$meta_key, true );
		if ( ! $markdown_source ) {
			return new WP_Error( 'missing-markdown-source', 'Markdown source is missing for post.' );
		}
		return $markdown_source;
	}
}

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
/**
 * Generate docs and create it as posts.
 *
 * @subcommand commands
 *
 * ## EXAMPLES
 *
 *     # Get value from config
 *     $ wp ee-docs commands
 */
function generate_ee_docs( $args, $assoc_arg ) {
	\WPOrg_Cli\Markdown_Import::action_wporg_cli_manifest_import();
}
// phpcs:enable

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'ee-docs', __NAMESPACE__ . '\generate_ee_docs' );
}
