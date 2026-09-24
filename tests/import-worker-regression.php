<?php
/** Run through: studio wp eval 'require .../tests/import-worker-regression.php;' */
if (!defined('WP_CLI') || !WP_CLI) exit;
require_once CUSTOMIFY_STARTER_SITES_PATH.'inc/generic/bootstrap.php';
use Customify_Starter_Sites\Jobs\{Job_Store,Importer_Runner};
use Customify_Starter_Sites\Studio\Remote_Client;
use Customify_Starter_Sites\Settings\Options_Store;
function import_assert($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$jobs=new Job_Store();
import_assert(!$jobs->has_active_job(),'no active import before isolated worker tests');
$latest=get_option(Job_Store::OPTION_LATEST,'');$created=[];
$client=new class(new Options_Store()) extends Remote_Client {
 public int $calls=0;public $nested=null;
 public function get(string $path,array $query=[]):array {
  ++$this->calls;
  if($this->nested){$cb=$this->nested;$this->nested=null;$cb();}
  return ['status'=>500,'error'=>'Intentional offline regression failure','body'=>null];
 }
};
$runner=new Importer_Runner($client,$jobs);
try {
 foreach([Job_Store::STATUS_COMPLETED,Job_Store::STATUS_FAILED,Job_Store::STATUS_CANCELLED] as $status){
  $id=$jobs->create(['template_id'=>67316]);$created[]=$id;$jobs->set_status($id,$status);$runner->run($id);
  import_assert($client->calls===0,'terminal callback performs no download or import: '.$status);
 }
 $id=$jobs->create(['template_id'=>67316]);$created[]=$id;
 $client->nested=static function()use($runner,$id){$runner->run($id);};
 $runner->run($id);
 import_assert($client->calls===1,'duplicate callback does not start a second worker');
 import_assert($jobs->get($id)['status']===Job_Store::STATUS_FAILED,'worker records failure from its own fetch');
 import_assert(!get_option(Job_Store::OPTION_WORKER),'exception releases worker claim');
 $owner=$jobs->create(['template_id'=>67316]);$created[]=$owner;
 import_assert($jobs->claim_worker($owner),'first worker claims site');
 import_assert(!$jobs->claim_worker($owner),'same job cannot claim twice');
 $rival=$jobs->create(['template_id'=>67316]);$created[]=$rival;
 $runner->run($rival);
 import_assert($jobs->get($rival)['status']===Job_Store::STATUS_FAILED && $client->calls===1,'competing job fails without starting an import');
 $jobs->release_worker($rival);
 import_assert(get_option(Job_Store::OPTION_WORKER)===$owner,'non-owner cannot release claim');
 $req=new WP_REST_Request('POST','/theme/jobs');$req->set_header('Content-Type','application/json');$req->set_body(wp_json_encode(['template_id'=>67316]));
 $controller=new Customify_Starter_Sites\REST\Job_Controller($jobs,$runner);
 $response=$controller->create_job($req);
 import_assert(is_wp_error($response)&&$response->get_error_code()==='custstsi_import_busy','second create request returns busy');
 import_assert($jobs->get($owner)['status']===Job_Store::STATUS_QUEUED,'busy request does not cancel existing job');
 $jobs->release_worker($owner);$jobs->set_status($owner,Job_Store::STATUS_CANCELLED);
 $pending=$jobs->create(['template_id'=>67316]);$created[]=$pending;
 $response=$controller->create_job($req);
 import_assert(is_wp_error($response)&&$response->get_error_code()==='custstsi_import_busy','queued job also blocks double-submit');
} finally {
 foreach($created as $id){$jobs->release_worker($id);delete_transient(Job_Store::TRANSIENT_PREFIX.$id);}
 if($latest)update_option(Job_Store::OPTION_LATEST,$latest,false);else delete_option(Job_Store::OPTION_LATEST);
}
