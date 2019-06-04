<?php
/**
 * File to register post type commands and its settings.
 *
 * @package ee-markdown-importer
 */

namespace WPOrg_Cli\Post_Types;

/**
 * Class to add post type Commands.
 */
class Post_Type_Commands {

	/**
	 * Variable to store post type name.
	 *
	 * @var string $post_type
	 */
	public $post_type = 'commands';

	/**
	 * Variable to store class object.
	 *
	 * @var object Singleton Object.
	 */
	private static $instance;

	/**
	 * Function to create Singleton object.
	 *
	 * @return object object of class
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Post_Type_Commands();
		}
		return self::$instance;
	}

	/**
	 * Post_Type_Commands constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'ee_register_post_type' ] );

		add_filter( 'template_include', [ $this, 'ee_template_include' ] );

		add_action( 'pre_get_posts', [ $this, 'ee_pre_get_posts' ] );

		add_filter( 'the_content', [ $this, 'ee_add_subcommands' ] );
	}

	/**
	 * Function to display command excerpt or content.
	 *
	 * @param string $excerpt_content Post excerpt or content.
	 * @param object $post            Post Object.
	 *
	 * @return string
	 */
	public static function ee_command_description( $excerpt_content, $post ) {

		if ( ! empty( $excerpt_content ) || ! $post instanceof \WP_Post ) {
			return $excerpt_content;
		}

		$content = $post->post_content;
		$content = rtrim( strtok( $content, "\n" ) );

		return $content;
	}

	/**
	 * Function to add commands sub-command.
	 *
	 * @param string $content Content for sub-command.
	 *
	 * @return string
	 */
	public function ee_add_subcommands( $content ) {

		if ( ! is_singular( 'commands' ) ) {
			return $content;
		}

		$post_id        = get_the_ID();
		$parent_page_id = wp_get_post_parent_id( $post_id );

		if ( empty( $parent_page_id ) ) {
			$parent_page_id = $post_id;
		}

		$args = [
			'post_type'      => $this->post_type,
			'post_parent'    => $parent_page_id,
			'posts_per_page' => 200, // phpcs:ignore
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$content_parts = explode( '<h3>GLOBAL PARAMETERS</h3>', $content );

		$subcommands = new \WP_Query( $args );

		$subcommands_content = '';

		$subcommands_exists = false;

		if ( $subcommands->have_posts() ) {
			$subcommands_content .= '<h3 id="subcommands">SUBCOMMANDS</h3>';
			$subcommands_content .= '<table class="wp-block-table ee-commands-table">';
			$subcommands_content .= '<thead>';
			$subcommands_content .= '<tr>';
			$subcommands_content .= '<th>Name</th>';
			$subcommands_content .= '<th>Description</th>';
			$subcommands_content .= '</tr>';
			$subcommands_content .= '</thead>';
			$subcommands_content .= '<tbody>';

			while ( $subcommands->have_posts() ) {
				$subcommands->the_post();

				if ( get_the_ID() === $post_id ) {
					continue;
				}

				$subcommands_exists = true;

				$subcommands_content .= '<tr>';
				$subcommands_content .= sprintf(
					'<td><a href="%s">%s</a></td>',
					get_the_permalink(),
					get_the_title()
				);

				remove_filter( 'the_content', [ $this, 'ee_add_subcommands' ] );
				add_filter( 'get_the_excerpt', [ $this, 'ee_command_description' ], 1, 2 );

				$subcommands_content .= sprintf( '<td>%s</td>', get_the_excerpt() );

				remove_filter( 'get_the_excerpt', [ $this, 'ee_command_description' ] );
				add_filter( 'the_content', [ $this, 'ee_add_subcommands' ] );

				$subcommands_content .= '</tr>';
			}

			wp_reset_postdata();
			$subcommands_content .= '</tbody>';
			$subcommands_content .= '</table>';
		}

		if ( count( $content_parts ) > 1 ) {
			$content           = $content_parts[0];
			$content          .= empty( $subcommands_exists ) ? '' : $subcommands_content;
			$global_parameters = empty( $content_parts[1] ) ? '' : '<h3>GLOBAL PARAMETERS</h3>' . $content_parts[1];
			$global_parameters = str_replace( '<table>', '<table class="wp-block-table ee-global-parameter-table">', $global_parameters );
			$global_parameters = str_replace( 'style="text-align: left"', '', $global_parameters );
			$global_parameters = str_replace( '<code>', '', $global_parameters );
			$content          .= $global_parameters;
		} else {
			$content  = $content_parts[0];
			$content .= empty( $subcommands_exists ) ? '' : $subcommands_content;
		}

		return $content;
	}

	/**
	 * Function to set post per page before query execution.
	 *
	 * @param string $query Query to get data.
	 *
	 * @return void
	 */
	public function ee_pre_get_posts( $query ) {

		if (
			is_admin() ||
			! $query->is_main_query() ||
			! is_post_type_archive( $this->post_type )
		) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
			return;
		}

		$query->set( 'posts_per_page', 200 );
		$query->set( 'post_parent', 0 );
	}

	/**
	 * Locate custom template for commands.
	 *
	 * @param string $template The path of the template to include.
	 *
	 * @return string The path of the template to include.
	 */
	public function ee_template_include( $template ) {

		if ( is_singular( $this->post_type ) ) {
			remove_filter( 'the_excerpt', 'wptexturize' );
			remove_filter( 'the_content', 'wptexturize' );
		}

		if ( ! is_post_type_archive( $this->post_type ) ) {
			return $template;
		}

		return EE_MARKDOWN_PLUGIN_DIR . '/page-templates/archive-commands.php';
	}

	/**
	 * Register post type commands
	 */
	public function ee_register_post_type() {
		$args = [
			'labels'              => [
				'name'               => esc_html_x( 'Commands', 'Post type general name', 'ee-markdown-importer' ),
				'singular_name'      => esc_html_x( 'Command', 'Post type singular name', 'ee-markdown-importer' ),
				'all_items'          => esc_html__( 'All Commands', 'ee-markdown-importer' ),
				'add_new'            => esc_html__( 'Add New', 'ee-markdown-importer' ),
				'add_new_item'       => esc_html__( 'Add New Command', 'ee-markdown-importer' ),
				'edit_item'          => esc_html__( 'Edit Command', 'ee-markdown-importer' ),
				'new_item'           => esc_html__( 'New Command', 'ee-markdown-importer' ),
				'view_item'          => esc_html__( 'View Command', 'ee-markdown-importer' ),
				'search_items'       => esc_html__( 'Search Commands', 'ee-markdown-importer' ),
				'not_found'          => esc_html__( 'No Commands found', 'ee-markdown-importer' ),
				'not_found_in_trash' => esc_html__( 'No Commands found in Trash', 'ee-markdown-importer' ),
				'not_found_in_trash' => esc_html__( 'No Commands found in Trash', 'ee-markdown-importer' ),
				'menu_name'          => esc_html_x( 'Commands', 'Admin Menu text', 'ee-markdown-importer' ),
			],
			'rewrite'             => [
				'slug'       => $this->post_type,
				'with_front' => false,
			],
			'show_in_rest'        => true,
			'public'              => true,
			'exclude_from_search' => false,
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => true,
			'has_archive'         => true,
			'supports'            => [ 'title', 'editor', 'excerpt', 'author', 'page-attributes' ],
			'hierarchical'        => true,
			'taxonomies'          => [ 'shows' ],
			'menu_icon'           => 'dashicons-admin-generic',
		];

		register_post_type( $this->post_type, $args );
	}

}
