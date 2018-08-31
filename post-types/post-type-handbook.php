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
	}

	/**
	 * Register post type commands
	 */
	public function ee_register_post_type() {

		$args = array(
			'labels'              => array(
				'name'               => __( 'Handbook', 'pmc-soaps' ),
				'singular_name'      => __( 'Handbook', 'pmc-soaps' ),
				'all_items'          => __( 'All Handbook', 'pmc-soaps' ),
				'add_new'            => __( 'Add New', 'pmc-soaps' ),
				'add_new_item'       => __( 'Add New Handbook', 'pmc-soaps' ),
				'edit_item'          => __( 'Edit Handbook', 'pmc-soaps' ),
				'new_item'           => __( 'New Handbook', 'pmc-soaps' ),
				'view_item'          => __( 'View Handbook', 'pmc-soaps' ),
				'search_items'       => __( 'Search Handbook', 'pmc-soaps' ),
				'not_found'          => __( 'No Handbook found', 'pmc-soaps' ),
				'not_found_in_trash' => __( 'No Handbook found in Trash', 'pmc-soaps' ),
				'menu_name'          => __( 'Handbook', 'pmc-soaps' ),
			),
			'public'              => true,
			'exclude_from_search' => false,
			'show_in_nav_menus'   => false,
			'publicly_queryable'  => true,
			'has_archive'         => true,
			'supports'            => array( 'title', 'editor', 'author', ),
			'taxonomies'          => array( 'shows' ),
			'menu_icon'           => 'dashicons-admin-generic',
		);

		register_post_type( $this->post_type, $args );
	}

}