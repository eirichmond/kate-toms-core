<?php
/**
 * Houses Filtered Results Block Template.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 */

// Get initial houses query
$args = array(
    'post_type' => 'house',
    'posts_per_page' => 12,
);

$query = new WP_Query($args);
?>

<div <?php echo get_block_wrapper_attributes(); ?> data-wp-interactive="kate-toms-house-filter">
    <?php if ($query->have_posts()) : ?>
    <div class="houses-grid">
        <?php while ($query->have_posts()) : $query->the_post(); ?>
        <article class="house-card">
            <?php if (has_post_thumbnail()) : ?>
            <div class="house-card__image">
                <?php the_post_thumbnail('medium'); ?>
            </div>
            <?php endif; ?>
            <div class="house-card__content">
                <h3 class="house-card__title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                <?php 
                        // Add any other house details you want to display
                        // Example: meta fields for location, size, etc.
                        ?>
            </div>
        </article>
        <?php endwhile; ?>
    </div>

    <?php if ($query->max_num_pages > 1) : ?>
    <div class="houses-pagination">
        <?php 
                // Add pagination if needed
                ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="houses-filter__no-results">
        <p>No houses found.</p>
    </div>
    <?php endif; ?>

    <?php wp_reset_postdata(); ?>

    <div class="houses-loading-overlay" data-wp-bind--hidden="!state.isLoading">
        <div class="houses-loading-spinner"></div>
    </div>
</div>