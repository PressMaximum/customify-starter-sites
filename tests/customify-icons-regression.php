<?php
if (!defined('WP_CLI') || !WP_CLI) exit;
require_once CUSTOMIFY_STARTER_SITES_PATH.'inc/generic/bootstrap.php';
if (get_template()!=='customify') throw new RuntimeException('Run with Customify active.');
$m=new ReflectionMethod(Customify_Starter_Sites\Steps\Options_Importer::class,'apply_customify_icons');$m->setAccessible(true);
$importer=new Customify_Starter_Sites\Steps\Options_Importer();
$before=get_option('customify_fa_ver',null);
$run=static function($theme)use($m,$importer){$w=[];$ok=$m->invokeArgs($importer,[['theme'=>$theme],&$w]);return [$ok,$w];};
$assert=static function($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";};
try {
 foreach(['v4','v6','v456'] as $v){[$ok,$w]=$run(['template'=>'customify','customify'=>['font_awesome_version'=>$v]]);$assert($ok&&!$w&&get_option('customify_fa_ver')===$v,'explicit library '.$v);}
 $icon=['type'=>'font-awesome-v456','icon'=>'fa-brands fa-tiktok'];
 foreach([[$icon],rawurlencode(wp_json_encode([$icon]))] as $mods){update_option('customify_fa_ver','v4');[$ok]=$run(['template'=>'customify','mods'=>['header_social_icons_items'=>$mods]]);$assert($ok&&get_option('customify_fa_ver')==='v456','legacy typed TikTok enables v6 with v4 shim');}
 [$ok]=$run(['template'=>'customify','mods'=>[]]);$assert(!$ok&&get_option('customify_fa_ver')==='v456','absent legacy icon data preserves destination setting');
 [$ok,$w]=$run(['template'=>'customify','customify'=>['font_awesome_version'=>'invalid']]);$assert(!$ok&&count($w)===1&&get_option('customify_fa_ver')==='v456','invalid value rejected');
 [$ok]=$run(['template'=>'other','customify'=>['font_awesome_version'=>'v4']]);$assert(!$ok&&get_option('customify_fa_ver')==='v456','other theme does not alter icon library');
} finally {if($before===null)delete_option('customify_fa_ver');else update_option('customify_fa_ver',$before);}
