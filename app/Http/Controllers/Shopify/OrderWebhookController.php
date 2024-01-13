<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderItems;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Revolution\Google\Sheets\Facades\Sheets;

class OrderWebhookController extends Controller
{
    //
    public function newOrder(Request $request)
    {
        $tab_name =Carbon::now()->format('F');
        //Need to add a google sheet ID here
        $sheet = Sheets::spreadsheet('');

        try {
            $sheet->addSheet($tab_name);
            $sheet->sheet($tab_name);
        } catch (\Exception $e) {
            if($e->getCode() == 400){
                $sheet->sheet($tab_name);
            }

        }

        $order_id=$this->processOrder($request,$tab_name);

        return response()->make($order_id,200);
    }

    public function processOrder(Request  $request,$tab_name){

        $payload = json_decode($request->getContent(), true);
        $order_id = $payload['id'];
        $date = date('d/m/Y', strtotime($payload['created_at']));
        $buyer_name = $payload['shipping_address']['name'];
        $address = $payload['shipping_address']['address1'];
        $city = $payload['shipping_address']['city'];
        $contact_no = $payload['shipping_address']['phone'];
        $total_amount = $payload['total_price'];
        $order_no = $payload['name'];
        $note = $payload['note'];
        $financial_status= $payload['financial_status'];
        if(isset($payload['gateway'])){
        $gateway= $payload['gateway'];
        }

        $utmParams = parse_url($payload['landing_site'], PHP_URL_QUERY);
        if(!isset($utmParams)){
            $utmParams=parse_url($payload['referring_site'], PHP_URL_QUERY);
        }

        $order = new Order();
        $order->order_id = $order_id;
        $order->date = $date;
        $order->buyer_name = $buyer_name;
        $order->address = $address;
        $order->destination_city = $city;
        $order->contact_no = $contact_no;
        $order->total_amount = $total_amount;
        $order->order_no = $order_no;
        $order->note = $note;
        $order_array = array('Date' => $date);
        $orders = [];
        $order->payload = $request->getContent();
        if ($order->save()) {
            $orderId = $order->id;
            foreach ($payload["line_items"] as $item) {
                $orderItem = new OrderItems();
                $product_name = $item["title"];
                $orderItem->order_id = $orderId;
                $orderItem->product_id = $item["product_id"];
                $orderItem->product_name = $product_name;
                $order_array['Product Name'] = $product_name;
                $variant = $item["variant_title"];
                $customise = "";
                $itemQty =$item["fulfillable_quantity"];
                if (!empty($variant)) {
                    $customise .= $variant
                        . PHP_EOL;
                }
                if (!empty($item["properties"])) {
                    foreach ($item["properties"] as $property) {
                        $customise .= $property["name"] . " : " . $property["value"] . PHP_EOL;
                    }
                }
                $order_array['Customise/Size'] = $customise;
                $order_array['Item Qty'] =$itemQty ;
                $order_array['Buyer Name'] = $buyer_name;
                $order_array['Address'] = $address;
                $order_array['destination city'] = $city;
                $order_array['Contact Number'] = $contact_no;
                $order_array['amount'] = $total_amount;
                $order_array['Financial Status'] = $financial_status;
                $order_array['Gateway'] = !isset($gateway) ? "" : $gateway;
                $order_array['order#'] = $order_no;
                $order_array['confirmed'] = "";
                $order_array['or not'] = "";
                $order_array['instruction'] = $note;
                $order_array['source'] = $utmParams;
                $orderItem->customise = $customise;
                $orderItem->item_qty =$itemQty;

                $orderItem->save();
                array_push($orders, $order_array);

            }
            //Need google sheet ID
            Sheets::spreadsheet('')->sheet($tab_name)->append($orders);
        }
        return $order_id;
    }
}
