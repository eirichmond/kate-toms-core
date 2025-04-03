<!-- houses-results/render.php -->

<?php 
var_dump($attributes);
?>

<div 
    data-wp-interactive="kate-toms-house-filter"
    data-wp-router-region="<?php echo esc_attr($attributes['regionId'] ?? wp_unique_id('houses-results-')); ?>"
    class="houses-results"
>
    <div 
        class="houses-results__content"
        data-wp-bind--hidden="state.isLoading"
    >
        <p><?php esc_html_e('Select filters to see houses...', 'kate-toms-core'); ?></p>
    </div>
    <div 
        class="houses-results__loading"
        data-wp-bind--hidden="!state.isLoading"
    >
        <p><?php esc_html_e('Loading houses...', 'kate-toms-core'); ?></p>
    </div>
</div>
