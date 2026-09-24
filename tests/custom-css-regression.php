<?php
if (!defined('WP_CLI') || !WP_CLI) exit;
require_once CUSTOMIFY_STARTER_SITES_PATH.'inc/generic/bootstrap.php';
$importer = new Customify_Starter_Sites\Steps\Options_Importer();
$method = new ReflectionMethod($importer, 'apply_custom_css');
$method->setAccessible(true);
$before = wp_get_custom_css();
$user = get_current_user_id();
$save = static function($css)use($importer,$method){$warnings=[];$result=$method->invokeArgs($importer,[['theme'=>['custom_css'=>$css]],[],&$warnings]);return [$result,$warnings];};
$assert = static function($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";};
try {
 wp_set_current_user(0); kses_init();
 $css = '.row > .item { width: 100%; } .x::before { content: "\\e900 &gt;"; }';
 $assert(strpos(wp_filter_post_kses(wp_slash($css)), '&gt; .item')!==false,'reproduces KSES encoding CSS child combinator');
 $filters = isset($GLOBALS['wp_filter']['wp_insert_post_data']) ? count($GLOBALS['wp_filter']['wp_insert_post_data']->callbacks[10]??[]) : 0;
 [$ok,$warnings]=$save($css);
 $assert($ok && !$warnings && wp_get_custom_css()===$css,'anonymous worker preserves combinators, escapes and literal entities');
 [$ok]=$save($css."\n.x > a { color: red; }");
 $assert($ok && wp_get_custom_css()===$css."\n.x > a { color: red; }",'repeated CSS import preserves exact text');
 $saved=wp_get_custom_css();
 foreach(['</style><script>alert(1)</script>','p{} </sty'] as $bad){
  [$ok,$warnings]=$save($bad);
  $assert(!$ok && count($warnings)===1 && wp_get_custom_css()===$saved,'rejects markup without replacing existing CSS');
 }
 $after = isset($GLOBALS['wp_filter']['wp_insert_post_data']) ? count($GLOBALS['wp_filter']['wp_insert_post_data']->callbacks[10]??[]) : 0;
 $assert($after===$filters,'temporary save hook removed after success and exception');
 $assert(false!==has_filter('content_save_pre','wp_filter_post_kses'),'HTML KSES stays active');
} finally {
 $save($before);
 wp_set_current_user($user); kses_init();
}
