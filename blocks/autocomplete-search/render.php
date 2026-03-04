<?php
/**
 * Autocomplete Search Block Template.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 */

$unique_id = wp_unique_id('autocomplete-search-');
$placeholder = $attributes['placeholder'] ?? 'Search houses, locations, and features...';
$show_categories = $attributes['showCategories'] ?? true;
$max_results = $attributes['maxResults'] ?? 30;

$context = array(
	'searchTerm' => '',
	'results' => array(),
	'groupedResults' => array(), // Empty array for grouped results
	'isOpen' => false,
	'isLoading' => false,
	'selectedIndex' => -1,
	'maxResults' => $max_results,
	'showCategories' => $show_categories,
);

$directive_context = wp_json_encode($context);
?>

<div
	<?php echo get_block_wrapper_attributes(); ?>
	data-wp-interactive="kate-toms-core/autocomplete-search"
	data-wp-context='<?php echo esc_attr($directive_context); ?>'
	data-wp-init="actions.init"
>
	<div class="autocomplete-search">
		<div class="autocomplete-search__input-container">
			<input
				id="<?php echo esc_attr($unique_id); ?>"
				type="text"
				class="autocomplete-search__input"
				placeholder="<?php echo esc_attr($placeholder); ?>"
				data-wp-bind--value="context.searchTerm"
				data-wp-on--input="actions.handleInput"
				data-wp-on--focus="actions.handleFocus"
				data-wp-on--blur="actions.handleBlur"
				data-wp-on--keydown="actions.handleKeyDown"
				autocomplete="off"
				role="combobox"
				aria-expanded="false"
				aria-haspopup="listbox"
				aria-owns="<?php echo esc_attr($unique_id); ?>-results"
				data-wp-bind--aria-expanded="context.isOpen"
			/>
			<div 
				class="autocomplete-search__loading"
				data-wp-class--is-visible="context.isLoading"
			>
				<span class="screen-reader-text">Loading...</span>
				<div class="autocomplete-search__spinner"></div>
			</div>
		</div>

		<div
			id="<?php echo esc_attr($unique_id); ?>-results"
			class="autocomplete-search__results"
			data-wp-class--is-open="context.isOpen"
			role="listbox"
			aria-label="Search results"
		>
			<template data-wp-each--group="context.groupedResults">
				<?php if ($show_categories): ?>
				<div class="autocomplete-search__result-category-header">
					<span data-wp-text="context.group.category"></span>
				</div>
				<?php endif; ?>
				<template data-wp-each--result="context.group.results">
					<div 
						class="autocomplete-search__result"
						data-wp-class--is-selected="callbacks.isSelected"
						data-wp-on--click="actions.selectResult"
						data-wp-on--mouseenter="actions.highlightResult"
						role="option"
						data-wp-bind--aria-selected="callbacks.isSelected"
					>
						<div class="autocomplete-search__result-content">
							<div class="autocomplete-search__result-image">
								<img 
									data-wp-bind--src="context.result.thumb"
									data-wp-bind--alt="context.result.label"
									loading="lazy"
								/>
							</div>
							<div class="autocomplete-search__result-text">
								<div 
									class="autocomplete-search__result-title"
									data-wp-text="context.result.label"
								></div>
								<div 
									class="autocomplete-search__result-desc"
									data-wp-text="context.result.desc"
								></div>
							</div>
						</div>
					</div>
				</template>
			</template>
		</div>
	</div>
</div>