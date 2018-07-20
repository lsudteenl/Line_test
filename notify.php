<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/unirest-php/src/Unirest.php';

function push_noti($linenotify_token){
    for($i=0;$i<count($linenotify_token);$i++)
    {
        $linenotiurl = "https://notify-api.line.me/api/notify";
        $headers = array("Authorization"=>"Bearer ".$linenotify_token[$i].'');
        $body = array('message' => 'เรียน Admin ขณะนี้มีผู้ใช้งาน แจ้งเปิด Ticket ใหม่ในกลุ่ม กรุณาตรวจสอบด้วยค่ะ ');
        $response = Unirest\Request::POST($linenotiurl, $headers,$body); 

        // echo $linenotify_token[$i]."</br>";
        // print_r ($response->body);
    }
}

?>