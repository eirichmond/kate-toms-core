import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';

/**
 * Persists the child blocks to post content so the dynamic render.php can read
 * them from $block->inner_blocks. The parent renders its own front-end markup,
 * so only the inner blocks need serialising here.
 *
 * @return {JSX.Element} Saved inner-blocks content.
 */
export default function save() {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );

	return <div { ...innerBlocksProps } />;
}
