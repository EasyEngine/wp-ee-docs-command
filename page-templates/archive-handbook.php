<?php
/**
 * The command cpt archive template file
 *
 * @package ee-markdown-importer
 */

get_header();

?>
	<main id="main" class="site-main" role="main">
		<div class="entry-content">
			<ul>
			<?php
			wp_list_pages(
				[
					'title_li'  => '',
					'post_type' => 'handbook',
				]
			);
			?>
			</ul>
		</div>
	</main><!-- #main -->

<?php
get_footer();
