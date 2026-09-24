<?php
if (!defined('WP_CLI') || !WP_CLI) exit;
require_once CUSTOMIFY_STARTER_SITES_PATH.'inc/generic/bootstrap.php';
use Customify_Starter_Sites\Jobs\{Import_Context,Job_Store,Importer_Runner};
use Customify_Starter_Sites\Studio\Remote_Client;
use Customify_Starter_Sites\Settings\Options_Store;
function context_assert($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$previous=get_current_user_id();wp_set_current_user(0);kses_init();
$context=new Import_Context();$post_id=0;
try {
 context_assert(!current_user_can('unfiltered_html'),'anonymous cron has no unfiltered capability initially');
 $context->enter();
 foreach(['unfiltered_html','upload_files','install_plugins','activate_plugins','edit_theme_options','edit_posts','publish_pages'] as $cap)context_assert(current_user_can($cap),'worker capability: '.$cap);
 context_assert(get_current_user_id()===0&&!current_user_can('promote_users'),'no user impersonation or user administration grant');
 $html='<!-- wp:group {"style":{"css":"color:red;"}} --><div class="wp-block-group"></div><!-- /wp:group --><svg viewBox="0 0 10 10"><path d="M0 0L10 10"/></svg><iframe src="https://example.org"></iframe>';
 $post_id=wp_insert_post(wp_slash(['post_type'=>'page','post_status'=>'draft','post_title'=>'Import context fixture','post_content'=>$html]),true);
 context_assert(!is_wp_error($post_id)&&get_post($post_id)->post_content===$html,'real post save preserves SVG, HTML and block CSS');
 kses_init();context_assert(false===has_filter('content_save_pre','wp_filter_post_kses'),'init refresh does not re-enable HTML stripping inside worker');
 register_post_type('custstsi_test',['capability_type'=>['fixture','fixtures'],'map_meta_cap'=>true]);
 context_assert(current_user_can('publish_fixtures'),'newly registered CPT capabilities are available');
 unregister_post_type('custstsi_test');
 $context->close();
 context_assert(!current_user_can('unfiltered_html')&&false!==has_filter('content_save_pre','wp_filter_post_kses'),'close restores capabilities and KSES');
} finally {if(is_int($post_id)&&$post_id>0)wp_delete_post($post_id,true);$context->close();wp_set_current_user($previous);}
$store=new Job_Store();context_assert(!$store->has_active_job(),'no active import before worker test');
$latest=get_option(Job_Store::OPTION_LATEST,'');$id=$store->create(['template_id'=>67316]);
$client=new class(new Options_Store()) extends Remote_Client {
 public bool $worker=false;
 public function get(string $path,array $query=[]):array {$this->worker=current_user_can('unfiltered_html')&&get_current_user_id()===0;throw new RuntimeException('Intentional fixture failure');}
};
try {
 wp_set_current_user(0);(new Importer_Runner($client,$store))->run($id);
 context_assert($client->worker,'real worker enters process context as user zero');
 context_assert($store->get($id)['status']===Job_Store::STATUS_FAILED,'worker reports thrown exception');
 context_assert(!current_user_can('unfiltered_html')&&!get_option(Job_Store::OPTION_WORKER)&&false!==has_filter('content_save_pre','wp_filter_post_kses'),'failed worker restores permissions, filters and lock');
} finally {wp_set_current_user($previous);$store->release_worker($id);delete_transient(Job_Store::TRANSIENT_PREFIX.$id);if($latest)update_option(Job_Store::OPTION_LATEST,$latest,false);else delete_option(Job_Store::OPTION_LATEST);}
