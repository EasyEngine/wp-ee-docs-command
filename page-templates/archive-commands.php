<?php
/**
 * The command cpt archive template file
 */

get_header();

?>
	<main id="main" class="site-main" role="main">
		<?php if ( have_posts() ) : ?>
			<div class="entry-content">
				<table class="wp-block-table ee-commands-table">
					<thead>
					<tr>
						<th><?php _e( 'Command', 'ee-markdown-importer' ); ?></th>
						<th><?php _e( 'Description', 'ee-markdown-importer' ); ?></th>
					</tr>
					</thead>
					<tbody>

					<?php /* Start the Loop */ ?>
					<?php while ( have_posts() ) : the_post(); ?>
						<tr>
							<td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
							<?php add_filter( 'get_the_excerpt', array( '\WPOrg_Cli\Post_Types\Post_Type_Commands', 'ee_command_description'), 1, 2 ); ?>
							<td><?php echo get_the_excerpt(); ?></td>
							<?php remove_filter( 'get_the_excerpt', array( '\WPOrg_Cli\Post_Types\Post_Type_Commands', 'ee_command_description') ); ?>
						</tr>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	</main><!-- #main -->

<?php
get_footer();
