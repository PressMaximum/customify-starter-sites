<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
require_once CUSTOMIFY_STARTER_SITES_PATH.'inc/generic/bootstrap.php';
$slug='cacheprobe'.substr(md5(uniqid('',true)),0,8);
$file=wp_tempnam('attribute-cache-test');$id=0;
try{
 if(wc_attribute_taxonomy_id_by_name($slug)!==0)throw new RuntimeException('Fixture already exists');
 file_put_contents($file,wp_json_encode(['woocommerce'=>['attributes'=>[['name'=>$slug,'label'=>'Cache probe']]]]));
 $jobs=new Customify_Starter_Sites\Jobs\Job_Store();
 $client=new Customify_Starter_Sites\Studio\Remote_Client(new Customify_Starter_Sites\Settings\Options_Store());
 $runner=new Customify_Starter_Sites\Jobs\Importer_Runner($client,$jobs);
 $method=new ReflectionMethod($runner,'import_woo_attributes');$method->setAccessible(true);
 $method->invoke($runner,$file,$jobs->latest()['id']);
 global $wpdb;$id=(int)$wpdb->get_var($wpdb->prepare("SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name=%s",$slug));
 if(!$id||wc_attribute_taxonomy_id_by_name($slug)!==$id)throw new RuntimeException('New attribute missing from primed ID cache');
 echo "PASS newly imported attribute resolves after priming empty WooCommerce ID cache\n";
}finally{
 if($id)wc_delete_attribute($id);
 if(taxonomy_exists('pa_'.$slug))unregister_taxonomy('pa_'.$slug);
 if(is_file($file))unlink($file);
 delete_transient('wc_attribute_taxonomies');WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
}
