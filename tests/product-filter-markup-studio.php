<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
require_once CUSTOMIFY_STARTER_SITES_PATH.'inc/generic/bootstrap.php';
$i=new Customify_Starter_Sites\Steps\Content_Importer();$m=new ReflectionMethod($i,'remap_query_block_ids');$m->setAccessible(true);
$input='<!-- wp:group --><div class="wp-block-group"><!-- wp:woocommerce/product-filters {"className":"demo-filter","showFilterDrawer":true} --><!-- wp:paragraph --><p>Filters</p><!-- /wp:paragraph --><!-- /wp:woocommerce/product-filters --></div><!-- /wp:group -->';
$out=$m->invoke($i,$input,[]);$blocks=parse_blocks($out);$filter=$blocks[0]['innerBlocks'][0];
$tests=[
 'normalizes nested filters without an ID map'=>str_contains($filter['attrs']['className'],'wc-block-product-filters'),
 'preserves existing class and drawer setting'=>str_contains($filter['attrs']['className'],'demo-filter')&&$filter['attrs']['showFilterDrawer']===true,
 'preserves inner content'=>$filter['innerBlocks'][0]['innerHTML']==='<p>Filters</p>',
 'repeated normalization is idempotent'=>$m->invoke($i,$out,[])===$out,
 'unrelated blocks unchanged'=>$m->invoke($i,'<!-- wp:paragraph --><p>Unchanged</p><!-- /wp:paragraph -->',[])==='<!-- wp:paragraph --><p>Unchanged</p><!-- /wp:paragraph -->',
];
foreach($tests as $label=>$ok){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$html=render_block($filter);
if(!preg_match('/class="[^"]*wc-block-product-filters(?: |")/',$html))throw new RuntimeException('Rendered filter lacks root class');
echo "PASS WooCommerce renderer retains canonical root class\n";
