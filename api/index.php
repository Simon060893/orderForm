<?php
//header("Content-Type: text/html; charset=windows-1251");
require 'PHPMailer-master/PHPMailerAutoload.php';
define('ADMIN_EMAIL', 'pasku@bk.ru');
$request = (object)$_REQUEST;
$post = json_decode(file_get_contents("php://input"));
$get = (object)$_GET;

function utf8ize($d)
{
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_string($d)) {
        return utf8_encode($d);
    }
    return $d;
}

function json_fix_cyr($json_str)
{
    $cyr_chars = array(
        '\u0430' => 'а', '\u0410' => 'А',
        '\u0431' => 'б', '\u0411' => 'Б',
        '\u0432' => 'в', '\u0412' => 'В',
        '\u0433' => 'г', '\u0413' => 'Г',
        '\u0434' => 'д', '\u0414' => 'Д',
        '\u0435' => 'е', '\u0415' => 'Е',
        '\u0451' => 'ё', '\u0401' => 'Ё',
        '\u0436' => 'ж', '\u0416' => 'Ж',
        '\u0437' => 'з', '\u0417' => 'З',
        '\u0438' => 'и', '\u0418' => 'И',
        '\u0439' => 'й', '\u0419' => 'Й',
        '\u043a' => 'к', '\u041a' => 'К',
        '\u043b' => 'л', '\u041b' => 'Л',
        '\u043c' => 'м', '\u041c' => 'М',
        '\u043d' => 'н', '\u041d' => 'Н',
        '\u043e' => 'о', '\u041e' => 'О',
        '\u043f' => 'п', '\u041f' => 'П',
        '\u0440' => 'р', '\u0420' => 'Р',
        '\u0441' => 'с', '\u0421' => 'С',
        '\u0442' => 'т', '\u0422' => 'Т',
        '\u0443' => 'у', '\u0423' => 'У',
        '\u0444' => 'ф', '\u0424' => 'Ф',
        '\u0445' => 'х', '\u0425' => 'Х',
        '\u0446' => 'ц', '\u0426' => 'Ц',
        '\u0447' => 'ч', '\u0427' => 'Ч',
        '\u0448' => 'ш', '\u0428' => 'Ш',
        '\u0449' => 'щ', '\u0429' => 'Щ',
        '\u044a' => 'ъ', '\u042a' => 'Ъ',
        '\u044b' => 'ы', '\u042b' => 'Ы',
        '\u044c' => 'ь', '\u042c' => 'Ь',
        '\u044d' => 'э', '\u042d' => 'Э',
        '\u044e' => 'ю', '\u042e' => 'Ю',
        '\u044f' => 'я', '\u042f' => 'Я',

        '\r' => '',
        '\n' => '<br />',
        '\t' => ''
    );

    foreach ($cyr_chars as $cyr_char_key => $cyr_char) {
        $json_str = str_replace($cyr_char_key, $cyr_char, $json_str);
    }
    return $json_str;
}

function jsonRemoveUnicodeSequences($struct)
{
    return preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", json_encode($struct));
}

function normJsonStr($str)
{
    $str = preg_replace_callback('/\\\\u([a-f0-9]{4})/i', create_function('$m', 'return chr(hexdec($m[1])-1072+224);'), $str);
    return iconv('cp1251', 'utf-8', $str);
}

function jsondecode($sText)
{
    if (!$sText) return false;
    $sText = iconv('cp1251', 'utf8', $sText);
    $aJson = json_decode($sText, true);
    $aJson = iconvarray($aJson);
    return $aJson;
}

function iconvarray($aJson)
{
    foreach ($aJson as $key => $value) {
        if (is_array($value)) {
            $aJson[$key] = iconvarray($value);
        } else {
            $aJson[$key] = iconv('utf8', 'cp1251', $value);
        }
    }
    return $aJson;
}

if (!empty($request->method) || !empty($post->method)) {
    $method = empty($request->method) ? $post->method : $request->method;
    $data = false;

    switch ($method) {
        case "order":
            $json_data = file_get_contents('../data/store.json');
            $servD = 'http://'.$_SERVER['SERVER_NAME'].'/project/orderForm/';

            $json_data = preg_replace(
                '/
                ^
                [\pZ\p{Cc}\x{feff}]+
                |
                [\pZ\p{Cc}\x{feff}]+$
               /ux',
                '',
                $json_data
            );
            $json_data = json_decode((($json_data)), true);
            $order_data = explode(",", $request->dataOrder);
//            var_dump($order_data);
            $totalPrice=0;
            $bodyText = '<style >.content{font-size: 24px;} </style>'.
                ' <div class="content">' .
                '<ul class="product-list">';

            foreach ($order_data as $key => $val) {
                foreach ($json_data as $k => $product) {
                    foreach ($product['products'] as $item => $iv) {

                        if ($iv['id'] == $val) {
                            $bodyText .= '<li><p class="title">' . $iv["info"]["title"] . '</p>' .
                                '<img style="width: 140px;" src="' .$servD.'data/images/'.$k.'/'. $iv["info"]["logo"] . '">' .
                                '<p class="price">Цена: <span style="color:#e164ef;font-family: fantasy;    font-size: 28px;">' . $iv["info"]["price"] . '</span> грн.</p>' .
                                ($iv["info"]["waranty"]?'<p class="waranty">Гарантия: <span style="color:#e164ef;font-family: fantasy;    font-size: 28px;">' . $iv["info"]["waranty"] . '</span> мес.</p>' :'').
                                ' </li><hr>';
                            $totalPrice += floatval($iv["info"]["price"]);
                            break;
                        }
                    }
                }
            }
            $bodyText .= '</ul>' .
                '<p>Общая сумма заказа: <span style="color:#0089dc;font-family: fantasy;    font-size: 28px;">'.$totalPrice.'грн.</p>'.
                ' </div>';

            $mail = new PHPMailer();
            $mail->CharSet = 'UTF-8';
            $mail->From = $request->fromEmail;
            $mail->FromName = $request->fromName;
            $mail->AddAddress(ADMIN_EMAIL); //recipient
            $mail->isHTML(true);
            $mail->Body = 'Пользователь <b>'.($request->fromName?$request->fromName:$request->fromEmail).'</b> сделал заказ:</br>'.$bodyText;
            $mail->Subject = 'Информация об заказе';

            if (!$mail->send()) {
                die(json_encode(array(
                    'error' => "Can not sent msg to admin", "text" => $mail->ErrorInfo
                )));
            } else {
                $mail = new PHPMailer();
                $mail->CharSet = 'UTF-8';
                $mail->From = ADMIN_EMAIL;
                $mail->FromName = $_SERVER['SERVER_NAME'];
                $mail->AddAddress($request->fromEmail); //recipient
                $mail->isHTML(true);
                $mail->Body = 'Ваш заказ на сайте '.$_SERVER['SERVER_NAME'].'</br>'.$bodyText;
                $mail->Subject = 'Информация об заказе';

                if (!$mail->send()) {
                    die(json_encode(array(
                        'error' => "Can not sent msg to user", "text" => $mail->ErrorInfo
                    )));
                } else {
                    die(json_encode(array(
                        'success' => "Спасибо за заказа. На вашу почту ".$request->fromEmail." отправленно письмо с информацией об Вашем заказе.Мы скоро с Вами свяжемся."
                    )));
                }
            }
            break;
        default:
            $data = array(
                'error' => "false request"
            );
            break;
    }
    echo(((jsonRemoveUnicodeSequences($data))));
} else {
    die(json_encode(array(
        'error' => "Empty request method!"
    )));
}

