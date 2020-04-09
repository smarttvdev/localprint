<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;


class PrintController extends Controller
{
    public function testPrint(Request $request){
        try {
            $connector = new FilePrintConnector("/dev/usb/lp0");
            $printer = new Printer($connector);
            $printer -> text("Hello World!\n");
            $printer -> cut();
            $printer -> close();

        } catch (Exception $e) {
            echo "Couldn't print to this printer: " . $e -> getMessage() . "\n";
        }
        return "success";

    }
    public function printOrder(Request $request){
        $input=$request->all();
        $order=$input['order_item'];
        $connection_type=$input['connection_type'];
        $printer_detail=$input['printer_detail'];
        $printer_type=$printer_detail['type'];
        $restaurant=$input['restaurant'];
        try {
            if($connection_type=="offline"){
                $path=$printer_detail['path'];
                $connector = new FilePrintConnector($path);
                if($printer_type=="XP58"){
                    $this->printXP58($connector,$order,$restaurant);
                }
            }
            else{
                //$connector = new NetworkPrintConnector("192.168.1.18", 9100);
            }
        } catch (Exception $e) {
            return [
                'status'=>'error',
                'msg'=>"Couldn't print to this printer: " . $e -> getMessage() . "\n"
            ];
        }
        return [
            "success"=>'success'
        ];
    }
    public function printOrderWithIp(Request $request){
        $order=$request->input('order_item');
        $printer_name=$request->input('printer_name');
        try {
            $connector = new WindowsPrintConnector($printer_name);
            $this->printXP58($connector,$order);
        } catch (Exception $e) {
            echo "Couldn't print to this printer: " . $e -> getMessage() . "\n";
        }
        return "success";
    }




    public function printXP58($connector,$order,$restaurant){
        // Logo and Restaurant Name, Address Part
//            $profile = CapabilityProfile::load("default");
        $remote_file_url = 'https://order.ohmani.com/public/Images/Icons/logo1578214810.png';
        $local_file =public_path('/Images/logo1578214810.png');
        $copy = copy( $remote_file_url, $local_file );
        if( !$copy ) {
            return "Doh! failed to copy file...\n";
        }
        else{
            return "WOOT! success to copy file...\n";
        }
        $printer -> close();



        $printer = new Printer($connector);
        $logo = EscposImage::load("public/Images/Icons/printer_logo.png", false);

        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer->bitImage($logo);
        $printer->setTextSize(2,2);
        $printer ->setEmphasis(false);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("$restaurant[restaurant_name]\n");


        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(1,1);
        $printer->text("Email: $restaurant[email]\n");
        $printer->text("Phone: $restaurant[phone_number]\n");


        $printer ->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        // End Logo Part

        // The part of Order Id, Date, Customer Name Part //
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer->feed(2);
        $printer->setEmphasis(true);
        $printer->text("Order#: $order[id]\n");
        $printer->setEmphasis(false);
        $order_time=(new \DateTime())->format('m/d/Y H:i A');
        $printer->text("Date: $order_time\n");
        $printer->text("Customer: $order[customer_name]\n");
        $printer->setEmphasis(true);
        $printer->text(str_pad('',32,'-')."\n");
        $printer->feed(1);
        // End the order information part

        // The part of order content
        $order_contents=$order['order_content'];
        foreach ($order_contents as $order_content){
            $printer->setEmphasis(true);
            $print_texts=$this->getRowItem($order_content['qty'],$order_content['productData']['name'],$order_content['price']);
            foreach ($print_texts as $print_text){
                $printer->text($print_text);
            }
            $printer->setEmphasis(false);
            $origin_texts=$this->getRowItem($order_content['qty'],'Main Food',$order_content['productData']['price']*$order_content['qty'],5,30);;
            foreach ($origin_texts as $origin_text){
                $printer->text($origin_text);
            }
            $option_tags=$order_content['productData']['option_tags'];
            foreach ($option_tags as $option_tag){
                $option_items=$option_tag['items'];
                foreach ($option_items as $option_item){
                    if($option_item['checked']){
                        $item_texts=$this->getRowItem($option_item['count'],$option_item['name'],$option_item['price']*$option_item['count'],5,30);
                        foreach ($item_texts as $item_text){
                            $printer->text($item_text);
                        }
                    }
                }
            }
            $printer->setEmphasis(true);
            $printer->text(str_pad('',32,'-')."\n");
        }
        // End of Order Content Part

        // Price Part
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $result_text=$this->getPriceRowItem('SubTotal',$order['amount']);
        $printer->text($result_text);
        $result_text=$this->getPriceRowItem('Sales Tax',$order['sales_tax']);
        $printer->text($result_text);

        $printer->setTextSize(2,2);
        $result_text=$this->getPriceRowItem('Total',$order['amount']+$order['sales_tax'],2);
        $printer->text($result_text);
        $printer->setEmphasis(true);
        $printer->setTextSize(1,1);
        $printer->text(str_pad('',32,'-')."\n");



        // End Price Part

        // Welcome part
        $printer->setEmphasis(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Thanks for visiting\n");
        $printer->text("Come back again\n");
        // End Welcome part
        $printer->feed(3);


        $printer -> close();
    }


    public function getRowItem($item_count,$item_name,$item_price,$left_spacing=0,$total_width=32){
        $left_text='';
        $left_text=str_pad($left_text,$left_spacing);
        $item_count=$left_text.$item_count."x ";
        $result=Array();

        $item_price="  $$item_price";
        $current_total_width=strlen($item_count.$item_price.$item_name);

        if($current_total_width<$total_width){
            $item_name=str_pad($item_name,$total_width-strlen($item_count.$item_price));
            $result[0]=$item_count.$item_name.$item_price."\n";
        }else{ // if the length of total is grater than the length of printer width;
            $item_name=wordwrap($item_name,$total_width-strlen($item_count.$item_price));
            $temps=explode("\n",$item_name);
            for($i=0;$i<count($temps);$i++){
                $item_count_space='';
                $item_count_space=str_pad($item_count_space,strlen($item_count));
                $item_name_temp=$temps[$i];
                if($i==0){
                    $item_name_temp=str_pad($item_name_temp,$total_width-strlen($item_count.$item_price));
                    $result[$i]=$item_count.$item_name_temp.$item_price."\n";
                }else{
                    $item_name_temp=$item_count_space.$item_name_temp;
                    $item_name_temp=str_pad($item_name_temp,$total_width);
                    $result[$i]=$item_name_temp."\n";
                }
            }
        }
        return $result;
    }

    public function getPriceRowItem($item_label,$item_price,$multipler=1,$total_letter_count=32){
        $result='';
        $item_label=$item_label.":";
        $item_price="$".$item_price;
        if ($multipler>=1)
            $total_letter_count/=$multipler;
        $item_label=str_pad($item_label,$total_letter_count-strlen($item_price));
        $result=$item_label.$item_price."\n";
        return $result;
    }
}
