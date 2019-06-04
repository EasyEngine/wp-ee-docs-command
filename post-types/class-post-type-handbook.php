<?php
/**
 * Function to add post type handbook
 *
 * @package ee-markdown-importer
 */

namespace WPOrg_Cli\Post_Types;

/**
 * Class to load post type handbook.
 */
class Post_Type_Handbook {

	/**
	 * Variable to store post type name.
	 *
	 * @var string $post_type
	 */
	public $post_type = 'handbook';

	/**
	 * Variable to store class object.
	 *
	 * @var object Singleton Object.
	 */
	private static $instance;

	/**
	 * Function to create Singleton class object.
	 *
	 * @return object object of class
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Post_Type_Handbook();
		}
		return self::$instance;
	}

	/**
	 * Post_Type_Handbook constructor.
	 */
	public function __construct() {

		add_action( 'init', [ $this, 'ee_register_post_type' ] );

		add_filter( 'template_include', [ $this, 'ee_template_include' ] );

		add_filter( 'the_content', [ $this, 'ee_add_subpages' ] );

		add_filter( 'wpghs_whitelisted_post_types', [ $this, 'ee_wpghs_whitelisted_post_types' ] );
	}

	/**
	 * Function to white list post type handbook.
	 *
	 * @param array $supported_post_types Array of supported post types.
	 *
	 * @return array
	 */
	public function ee_wpghs_whitelisted_post_types( $supported_post_types ) {

		return [
			'handbook',
		];
	}

	/**
	 * Locate custom template for commands.
	 *
	 * @param string $template The path of the template to include.
	 *
	 * @return string The path of the template to include.
	 */
	public function ee_template_include( $template ) {

		if ( ! is_post_type_archive( $this->post_type ) ) {
			return $template;
		}

		return EE_MARKDOWN_PLUGIN_DIR . '/page-templates/archive-handbook.php';
	}

	/**
	 * Function to add subpage in handbook post type.
	 *
	 * @param string $content Sub page content.
	 *
	 * @return string
	 */
	public function ee_add_subpages( $content ) {

		if ( ! is_singular( $this->post_type ) ) {
			return $content;
		}

		$github_links = '';

		if ( function_exists( 'get_the_github_edit_link' ) ) {
			$edit_url      = get_the_github_edit_url();
			$github_links .= sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit this on Github', 'ee-markdown-importer' )
			);
		}

		if ( ! empty( $github_links ) ) {
			$temp_content = $content;
			$content      = empty( $github_links ) ? '' : '<p>' . $github_links . '</p>';
			$content     .= $temp_content;
		}

		$post_id        = get_the_ID();
		$parent_page_id = wp_get_post_parent_id( $post_id );

		if ( empty( $parent_page_id ) ) {
			$parent_page_id = $post_id;
		}

		$page_list = wp_list_pages(
			[
				'title_li'  => '',
				'post_type' => 'handbook',
				'child_of'  => $parent_page_id,
				'echo'      => false,
				'exclude'   => $post_id,
			]
		);

		if ( empty( $page_list ) ) {
			return $content;
		}

		$content .= '<h3 id="subpages" class="">Subpages</h3>';
		$content .= '<ul>';
		$content .= $page_list;
		$content .= '</ul>';

		return $content;
	}

	/**
	 * Register post type commands
	 *
	 * @return void
	 */
	public function ee_register_post_type() {

		$args = [
			'labels'              => [
				'name'               => esc_html_x( 'Handbook', 'Post type general name', 'ee-markdown-importer' ),
				'singular_name'      => esc_html_x( 'Handbook', 'Post type singular name', 'ee-markdown-importer' ),
				'all_items'          => esc_html__( 'All Handbook', 'ee-markdown-importer' ),
				'add_new'            => esc_html__( 'Add New', 'ee-markdown-importer' ),
				'add_new_item'       => esc_html__( 'Add New Handbook', 'ee-markdown-importer' ),
				'edit_item'          => esc_html__( 'Edit Handbook', 'ee-markdown-importer' ),
				'new_item'           => esc_html__( 'New Handbook', 'ee-markdown-importer' ),
				'view_item'          => esc_html__( 'View Handbook', 'ee-markdown-importer' ),
				'search_items'       => esc_html__( 'Search Handbook', 'ee-markdown-importer' ),
				'not_found'          => esc_html__( 'No Handbook found', 'ee-markdown-importer' ),
				'not_found_in_trash' => esc_html__( 'No Handbook found in Trash', 'ee-markdown-importer' ),
				'menu_name'          => esc_html_x( 'Handbook', 'Admin Menu text', 'ee-markdown-importer' ),
			],
			'rewrite'             => [
				'slug'       => 'handbook',
				'with_front' => false,
			],
			'show_in_rest'        => true,
			'public'              => true,
			'exclude_from_search' => false,
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => true,
			'has_archive'         => true,
			'supports'            => [ 'title', 'editor', 'excerpt', 'author', 'page-attributes', 'revisions' ],
			'hierarchical'        => true,
			'taxonomies'          => [ 'shows' ],
			'menu_icon'           => 'dashicons-admin-generic',
		];

		register_post_type( $this->post_type, $args );
	}

}
