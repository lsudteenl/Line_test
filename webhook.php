<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/mail.php';
require_once dirname(__FILE__) . '/notify.php';

$content = file_get_contents('php://input');

$line_json     = json_decode($content, true);
$line_url      = "https://api.line.me/v2/bot/message/reply";
$line_header   = array();
$line_header[] = "Content-Type: application/json";
$line_header[] = "Authorization: Bearer {$line_AccessToken}";
//$line_message = $line_json['events'][0]['message']['text'];

$input_word = $line_json['events'][0]['message']['text'];
$input_type = $line_json['events'][0]['message']['type'];
$input_id   = $line_json['events'][0]['message']['id'];


$input_keycode = substr($input_word, 0, strpos($input_word, " "));
$input_title   = substr($input_word, strpos($input_word, " ") + 1);


$arrPostData               = array();
$arrPostData['replyToken'] = $line_json['events'][0]['replyToken'];
$userid                    = $line_json['events'][0]['source']['userId'];
$conn                      = new mysqli($dbhost, $dbusername, $dbpassword, $dbname);

$sql    = "SELECT * from admin where linecode='$userid' And Role='admin'";
$result = $conn->query($sql);
if ($result->num_rows > 0) { //เป็น Admin โดน ID ตรงกับที่บันทึกไว้
    $sql    = "SELECT * from chatlog where userid='$input_word' ORDER BY timestamp ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) { //เป็น Admin พิมพ์ User ID ของผู้ใช้งาน เพื่อจะเปิด Ticket
        $sendfiles    = array();
        $subject      = '';
        $html_content = '<h1>ประวัติการสนทนาทั้งหมด</h1></br>';
        $html_content .= '<h2>User ID: ' . $input_word . '</h2></br>';
        
        while ($row = $result->fetch_assoc()) {
            if ($row['msgtype'] == "text") {
                $html_content .= '<p>' . $row['timestamp'] . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;💬&nbsp;' . $row['msgcontent'] . '</p>';
            } elseif ($row['msgtype'] == "image") {
                $html_content .= '<p>' . $row['timestamp'] . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;🖼 &nbsp;' . '(รูปภาพ ' . $row['msgid'] . ')</p>';
                array_push($sendfiles, dirname(__FILE__) . '/content' . '/' . $row['msgid'] . '.png');
            } elseif ($row['msgtype'] == "video") {
                $html_content .= '<p>' . $row['timestamp'] . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;🎞&nbsp;' . '(วีดีโอ ' . $row['msgid'] . ')</p>';
                array_push($sendfiles, dirname(__FILE__) . '/content' . '/' . $row['msgid'] . '.mp4');
            } elseif ($row['msgtype'] == "start") {
                $subject = 'Ticket: ' . $row['msgid'] . '  ' . $row['msgcontent'];
            }
        }
        $html_content .= '<p><b>ไฟล์ที่แนบมา : </b>' . count($sendfiles) . ' ไฟล์</p>';
        $send_email = multi_attach_mail($to, $subject, $html_content, $from, $from_name, $sendfiles); //ส่งประวัติการสนทนา ไปยังอีเมล์
        

        $sql    = "SELECT * from chatlog where userid='$input_word' AND msgtype!='start'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            // output data of each row
            while($row = $result->fetch_assoc()) {
                array_map('unlink', glob(dirname(__FILE__) . '/content'.'/'.$row['msgid'].'.*'));
            }
        }

        $sql    = "DELETE from chatlog where userid='$input_word'";
        $conn->query($sql);

        $arrPostData['messages'][0]['type'] = "text";
        $arrPostData['messages'][0]['text'] = "✔️  Open E-Ticket Success !";
        
    } else {
        
    }
} else { // เป็นผู้ใช้งานธรรมดา
    $sql    = "SELECT * from chatlog where userid='$userid' AND msgtype='start'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) { //เคยเปิด Ticket ต้างไว้แล้ว
        if ($input_word == "ยกเลิก ticket" || $input_word == "ยกเลิก Ticket") { //เคยเปิด Ticket ต้างไว้แล้ว และต้องการลบ Ticket ที่เปิดค้างไว้
            if ($result->num_rows > 0) {
                // $sql    = "SELECT * from chatlog where userid='$userid' AND msgtype!='start'";
                // $result = $conn->query($sql);
                // if ($result->num_rows > 0) {
                //     // output data of each row
                //     while($row = $result->fetch_assoc()) {
                //         if($row['msgtype']=="image"||$row['msgtype']=="video"){
                
                //         }
                //     }
                // }
                $sql = "DELETE from chatlog where userid='$userid'";
                $conn->query($sql);
                $arrPostData['messages'][0]['type'] = "text";
                $arrPostData['messages'][0]['text'] = "☑️  ดำเนินการลบ Ticket เดิมเรียบร้อยแล้วค่ะ ท่านสามารถส่งข้อมูลใหม่ได้ โดยการเปิด Ticket ใหม่ค่ะ";
            }
        } elseif ((preg_match("/^IT/", $input_keycode) == true)) { //เคยเปิด Ticket ต้างไว้แล้ว แต่พิมพ์คีย์เวิร์ดมาอีก
            
            $searchthis = $input_keycode;
            $key_result = array();
            $sendback   = "";
            $handle     = @fopen("keycode.dat", "r");
            if ($handle) {
                while (!feof($handle)) {
                    $buffer = fgets($handle);
                    if (strpos($buffer, $searchthis) !== FALSE)
                        $key_result[] = $buffer;
                }
                fclose($handle);
            }
            
            if (strlen($key_result[0]) > 0 && strlen($input_title) > 0) { //เคยเปิด Ticket ต้างไว้แล้ว แต่พิมพ์เปิด Ticket ใหม่อีกครั้งโดยที่ยังไม่ได้ปิดของเดิม
                $sql    = "SELECT * from chatlog where userid='$userid' AND msgtype='start'";
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
                    $arrPostData['messages'][0]['type'] = "text";
                    $arrPostData['messages'][0]['text'] = $userid;
                    $arrPostData['messages'][1]['type'] = "text";
                    $arrPostData['messages'][1]['text'] = "คุณเคยเปิด Ticket กับทางเราแล้ว ระบบจะดำเนินการต่อจากครั้งที่แล้วนะคะ หากต้องการยกเลิก Ticket อันเก่า ให้พิมพ์คำว่า 'ยกเลิก ticket' ค่ะ";
                }
            }
        } else { //เคยเปิด Ticket ต้างไว้แล้ว และเก็บข้อมูลที่ส่งมาลง Data base
            $sql = "INSERT INTO chatlog(userid, msgtype, msgid,msgcontent) VALUES ('$userid', '$input_type', '$input_id','$input_word')";
            $conn->query($sql);
            
            if ($input_type == "image") { 
                $remote_url = "https://api.line.me/v2/bot/message/" . $input_id . "/content";
                $context    = stream_context_create(array(
                    'http' => array(
                        'header' => "Authorization: Bearer  " . $line_AccessToken
                    )
                ));
                $fileName   = file_get_contents($remote_url, false, $context);
                file_put_contents(dirname(__FILE__) . '/content' . '/' . $input_id . '.png', $fileName);
                
            } elseif ($input_type == "video") {
                $remote_url = "https://api.line.me/v2/bot/message/" . $input_id . "/content";
                $context    = stream_context_create(array(
                    'http' => array(
                        'header' => "Authorization: Bearer  " . $line_AccessToken
                    )
                ));
                $fileName   = file_get_contents($remote_url, false, $context);
                file_put_contents(dirname(__FILE__) . '/content' . '/' . $input_id . '.mp4', $fileName);
                
            } elseif ($input_type == "text") {
                // $sql    = "SELECT * from chatlog where userid='$userid' AND msgtype='start'";

            }
            
        }
        
        
    } elseif (preg_match("/^IT/", $input_keycode) == true||preg_match("/^it/", $input_keycode) == true) { //ยังไม่เคยเปิด Ticket แต่พิมพ์คียเวิร์ดมา
        $searchthis = $input_keycode;
        $key_result = array();
        $sendback   = "";
        $handle     = @fopen("keycode.dat", "r");
        if ($handle) {
            while (!feof($handle)) {
                $buffer = fgets($handle);
                if (strpos($buffer, $searchthis) !== FALSE)
                    $key_result[] = $buffer;
            }
            fclose($handle);
        }
        
        if (strlen($key_result[0]) > 0 && strlen($input_title) > 2) { //คีย์เวิร์ดตรงกับที่ตั้งไว้
            $userid = $line_json['events'][0]['source']['userId'];
            $sql    = "SELECT * from chatlog where userid='$userid' AND msgtype='start'";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                $arrPostData['messages'][0]['type'] = "text";
                $arrPostData['messages'][0]['text'] = "คุณเคยเปิด Ticket กับทางเราแล้ว ระบบจะดำเนินการต่อจากครั้งที่แล้วนะคะ หาต้องการยกเลิก Ticket อันเก่า ให้พิมพ์คำว่า 'ยกเลิก ticket'";
            } else {
                $arrPostData['messages'][0]['type'] = "text";
                $arrPostData['messages'][0]['text'] = $userid;
                $arrPostData['messages'][1]['type'] = "text";
                $arrPostData['messages'][1]['text'] = "สวัสดีค่ะ รบกวนขอรายละเอียด และรูปภาพเพิ่มเติมด้วยค่ะ 😃";
                
                $sql = "INSERT INTO chatlog(userid, msgtype, msgid,msgcontent)
                    VALUES ('$userid', 'start', '$input_keycode','$input_title')";
                $conn->query($sql);

                push_noti($linenotify_token);
                
            }
            /// Notify Code Here
        } else { // พิมพ์คีย์เวิร์ดมา แต่ไม่ตรงกับที่ระบบตั้งไว้
            $arrPostData['messages'][0]['type'] = "text";
            $arrPostData['messages'][0]['text'] = "ข้อมูลที่ท่านกรอกไม่ตรงตาม Format ค่ะ ฟอร์แมตจะเป็นดังนี้ค่ะ IT/DCXX/VXX ปัญหาที่เกิด [ตัวอย่าง: IT/DCUB/V20 ไม่สามารถเข้าสู่ระบบได้ สอบสอบให้หน่อยค่ะ]";
        }
        
    } elseif ($input_word == "ยกเลิก ticket" || $input_word == "ยกเลิก Ticket") { //ยังไม่ได้เปิด Ticket (ไม่มีข้อมูลในฐานข้อมูล) แต่ อยากจะลบ Ticket
        $arrPostData['messages'][0]['type'] = "text";
        $arrPostData['messages'][0]['text'] = "เราไม่พบประวัติการสนทนากับทคุณค่ะ คุณสามารถเปิด Ticket ใหม่ ได้โดยฟอร์แมตจะเป็นดังนี้ค่ะ IT/DCXX/VXX ปัญหาที่เกิด [ตัวอย่าง: IT/DCUB/V20 ไม่สามารถเข้าสู่ระบบได้ค่ะ รบกวนตรวจสอบด้วย]";
    }
    elseif ($input_word == "id ของฉัน" || $input_word == "ไอดีของฉัน") { //ยังไม่ได้เปิด Ticket (ไม่มีข้อมูลในฐานข้อมูล) แต่ อยากจะลบ Ticket
        $arrPostData['messages'][0]['type'] = "text";
        $arrPostData['messages'][0]['text'] = "ไอดีของคุณคือ : ".$userid;
    }
}

$conn->close();




//CallAPI('POST',$line_url,$arrPostData,$line_header);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $line_url);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_HTTPHEADER, $line_header);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arrPostData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);
?>
