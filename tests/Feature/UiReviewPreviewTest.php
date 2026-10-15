<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
class UiReviewPreviewTest extends TestCase {
 use RefreshDatabase;
 public function test_render_module_previews(): void {
  $dir=storage_path('app/ui-review'); if(!is_dir($dir)) mkdir($dir,0777,true);
  $user=User::factory()->create(['name'=>'Admin Preview','email'=>'preview@example.test','role'=>'admin']);
  $this->actingAs($user);
  foreach(['pending','approved'] as $status) {
   $order=DeliveryOrder::create(['sales_user_id'=>$user->id,'customer_name'=>'Customer Contoh untuk Pemeriksaan Tampilan','delivery_date'=>now()->addDay()->toDateString(),'status'=>$status]);
   DeliveryOrderItem::create(['delivery_order_id'=>$order->id,'part_number'=>'PART-CONTOH-001','quantity'=>1200]);
  }
  $routes=['dashboard','users.index','users.create','locations.index','part-settings.index','part-settings.create','stock-input.index','stock-view.index','merge-pallet.index','move-pallet.index','box-not-full.create','box-not-full.approvals','delivery.index','delivery.create','delivery.approvals','delivery-assign.index','delivery.pick.verification','delivery.pick.issues','delivery.pick.locks','reports.stock-input','reports.withdrawal','audit.index','access.index','profile.edit'];
  foreach($routes as $route) {
   $response=$this->get(route($route)); $response->assertOk();
   $html=str_replace('http://localhost/', '/', $response->getContent());
   file_put_contents($dir.'/'.$route.'.html', $html);
  }
  $this->post(route('logout'));
  $response=$this->get(route('login'));$response->assertOk();
  file_put_contents($dir.'/login.html',str_replace('http://localhost/','/',$response->getContent()));
 }
}
