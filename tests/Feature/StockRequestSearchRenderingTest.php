<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;
class StockRequestSearchRenderingTest extends TestCase
{
    public function test_fuzzy_catalog_is_safely_encoded_and_does_not_render_thousands_of_forms(): void
    {
        $this->actingAs(new User(['name'=>'Search test','access_level'=>User::CASHIER]));
        $name='</script><img src=x onerror=alert(1)> & "quoted"';
        $products=collect(range(1,2910))->map(fn($id)=>['id'=>$id,'name'=>$name,'code'=>(string)$id,'brand'=>'Test']);
        $html=view('stock.transfers',[
            'shopId'=>null,'shops'=>collect(),'catalog'=>['products'=>$products,'shops'=>[],'stock'=>[],'receivingShopId'=>null],
            'transfers'=>new LengthAwarePaginator([],0,50),'events'=>collect(),'errors'=>new ViewErrorBag(),
        ])->render();
        $this->assertStringNotContainsString('</script><img',$html);
        $this->assertStringNotContainsString('name="request_key"',$html);
        preg_match('/<script type="application\/json" id="transferCatalog"[^>]*>(.*?)<\/script>/s',$html,$matches);
        $data=json_decode($matches[1],true,512,JSON_THROW_ON_ERROR);
        $this->assertCount(2910,$data['products']);
        $this->assertSame($name,$data['products'][2909]['name']);
        $this->assertStringContainsString('stock-request-search.js',$html);
        $this->assertLessThan(strpos($html,'stock-request-search.js'),strpos($html,'product-search.js'));
    }
}
