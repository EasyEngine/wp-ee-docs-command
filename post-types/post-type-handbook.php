<?php
namespace WPOrg_Cli\Post_Types;

class Post_Type_Handbook {

	/**
	 * @var string $post_type
	 */
	public $post_type = 'handbook';

	/**
	 * @var object Singleton Object.
	 */
	private static $instance;

	/**
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

		add_action( 'init', array( $this, 'ee_register_post_type' ) );

		add_filter( 'template_include', array( $this, 'ee_template_include' ) );

		add_filter( 'the_content', array( $this, 'ee_add_subpages' ) );

		add_filter( 'wpghs_whitelisted_post_types', array( $this, 'ee_wpghs_whitelisted_post_types' ) );
	}

	function ee_wpghs_whitelisted_post_types( $supported_post_types ) {

		return array(
			'handbook',
		);
	}

	/**
	 * Locate custom template for commands.
	 *
	 * @param string $template The path of the template to include.
	 *
	 * @return string The path of the template to include.
	 */
	function ee_template_include( $template ) {

		if ( ! is_post_type_archive( $this->post_type ) ) {
			return $template;
		}

		return EE_MARKDOWN_PLUGIN_DIR . '/page-templates/archive-handbook.php';
	}

	function ee_add_subpages( $content ) {

		if ( ! is_singular( $this->post_type ) ) {
			return $content;
		}

		$github_links = '';

		if ( function_exists( 'get_the_github_edit_link' ) ) {
			$edit_url = get_the_github_edit_url();
			$github_links .= sprintf( '<a href="%s">Edit this on Github</a>', esc_url( $edit_url ) );
		}

		if ( ! empty( $github_links ) ) {
			$temp_content = $content;
			$content      = empty( $github_links ) ? '' : '<p>' . $github_links . '</p>';
			$content      .= $temp_content;
		}

		$post_id        = get_the_ID();
		$parent_page_id = wp_get_post_parent_id( $post_id );

		if ( empty( $parent_page_id ) ) {
			$parent_page_id = $post_id;
		}

		$page_list = wp_list_pages(
			array(
				'title_li'  => '',
				'post_type' => 'handbook',
				'child_of'  => $parent_page_id,
				'echo'      => false,
				'exclude'   => $post_id,
			)
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
	 */
	public function ee_register_post_type() {

		$args = array(
			'labels'              => array(
				'name'               => __( 'Handbook', 'ee-markdown-importer' ),
				'singular_name'      => __( 'Handbook', 'ee-markdown-importer' ),
				'all_items'          => __( 'All Handbook', 'ee-markdown-importer' ),
				'add_new'            => __( 'Add New', 'ee-markdown-importer' ),
				'add_new_item'       => __( 'Add New Handbook', 'ee-markdown-importer' ),
				'edit_item'          => __( 'Edit Handbook', 'ee-markdown-importer' ),
				'new_item'           => __( 'New Handbook', 'ee-markdown-importer' ),
				'view_item'          => __( 'View Handbook', 'ee-markdown-importer' ),
				'search_items'       => __( 'Search Handbook', 'ee-markdown-importer' ),
				'not_found'          => __( 'No Handbook found', 'ee-markdown-importer' ),
				'not_found_in_trash' => __( 'No Handbook found in Trash', 'ee-markdown-importer' ),
				'menu_name'          => __( 'Handbook', 'ee-markdown-importer' ),
			),
			'rewrite'             => array(
				'slug'       => 'handbook',
				'with_front' => false
			),
			'show_in_rest'        => true,
			'public'              => true,
			'exclude_from_search' => false,
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => true,
			'has_archive'         => true,
			'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'page-attributes', 'revisions' ),
			'hierarchical'        => true,
			'taxonomies'          => array( 'shows' ),
			'menu_icon'           => 'dashicons-admin-generic',
		);

		register_post_type( $this->post_type, $args );
	}

}