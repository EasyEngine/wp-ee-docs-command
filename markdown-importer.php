<?php
/**
 * Plugin name: Easy-Engine Markdown Importer.
 * Description: Import markdown from github repo to wordpress site.
 * Version:     0.1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */
require_once  __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/class-markdown.php';
require_once __DIR__ . '/inc/class-handbook.php';
require_once __DIR__ . '/inc/class-shortcodes.php';

// register handbook cpt
function ee_register_handbook_cpt() {
	$args = array(
		'labels'              => array(
			'name'               => __( 'Handbook', 'pmc-soaps' ),
			'singular_name'      => __( 'Handbook', 'pmc-soaps' ),
			'all_items'          => __( 'All HandbookS', 'pmc-soaps' ),
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
		'rewrite'             => array(
			'slug' => 'handbook',
		),
	);

	register_post_type( 'handbook', $args );
}

add_action( 'init', 'ee_register_handbook_cpt' );
/**
 * Registry of actions and filters
 */
add_action( 'init', array( 'WPOrg_Cli\Markdown_Import', 'action_init' ) );
add_action( 'init', array( 'WPOrg_Cli\Shortcodes', 'action_init' ) );
add_action( 'wporg_cli_all_import', array( 'WPOrg_Cli\Markdown_Import', 'action_wporg_cli_manifest_import' ) );
add_action( 'wporg_cli_markdown_import', array( 'WPOrg_Cli\Markdown_Import', 'action_wporg_cli_markdown_import' ) );
add_action( 'load-post.php', array( 'WPOrg_Cli\Markdown_Import', 'action_load_post_php' ) );
add_action( 'edit_form_after_title', array( 'WPOrg_Cli\Markdown_Import', 'action_edit_form_after_title' ) );
add_action( 'save_post', array( 'WPOrg_Cli\Markdown_Import', 'action_save_post' ) );
add_filter( 'cron_schedules', array( 'WPOrg_Cli\Markdown_Import', 'filter_cron_schedules' ) );
add_filter( 'the_title', array( 'WPOrg_Cli\Handbook', 'filter_the_title_edit_link' ), 10, 2 );
add_filter( 'get_edit_post_link', array( 'WPOrg_Cli\Handbook', 'redirect_edit_link_to_github' ), 10, 3 );
add_filter( 'o2_filter_post_actions', array( 'WPOrg_Cli\Handbook', 'redirect_o2_edit_link_to_github' ), 11, 2 );
add_action( 'wp_head', function(){
	?>
	<style>
		pre code {
			line-height: 16px;
		}
		a.github-edit {
			margin-left: .5em;
			font-size: .5em;
			vertical-align: top;
			display: inline-block;
			border: 1px solid #eeeeee;
			border-radius: 2px;
			background: #eeeeee;
			padding: .5em .6em .4em;
			color: black;
			margin-top: 0.1em;
		}
		a.github-edit > * {
			opacity: 0.6;
		}
		a.github-edit:hover > * {
			opacity: 1;
			color: black;
		}
		a.github-edit img {
			height: .8em;
		}
		.single-handbook div.table-of-contents {
			margin: 0;
			float: none;
			padding: 0;
			border: none;
			box-shadow: none;
			width: auto;
		}
		.single-handbook div.table-of-contents:after {
			content: " ";
			display: block;
			clear: both;
		}
		.single-handbook .table-of-contents h2 {
			display: none;
		}
		.single-handbook div.table-of-contents ul {
			padding: 0;
			margin-top: 0.4em;
			margin-bottom: 1.1em;
		}
		.single-handbook div.table-of-contents > ul li {
			display: inline-block;
			padding: 0;
			font-size: 12px;
		}
		.single-handbook div.table-of-contents > ul li a:after {
			content: "|";
			display: inline-block;
			width: 20px;
			text-align: center;
			color: #eeeeee
		}
		.single-handbook div.table-of-contents > ul li:last-child a:after {
			content: "";
		}
		.single-handbook div.table-of-contents ul ul {
			display: none;
		}
		.single-handbook #secondary {
			max-width: 240px;
		}
	</style>
	<?php
});