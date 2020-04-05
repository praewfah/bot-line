<?php

require __DIR__ . '/vendor/autoload.php';

use \LINE\LINEBot\SignatureValidator as SignatureValidator;

// load config
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// initiate app
$configs =  [
    'settings' => ['displayErrorDetails' => true],
];
$app = new Slim\App($configs);

/* ROUTES */
$app->get('/', function ($request, $response) {  
    return "Chat bot : เลขมงคล <br> Welcome!";
});

$app->post('/', function ($request, $response)
{
    // get request body and line signature header
    $body = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_LINE_SIGNATURE'];

    // log body and signature
    file_put_contents('php://stderr', 'Body: '.$body);

    // is LINE_SIGNATURE exists in request header?
    if (empty($signature)){
        return $response->withStatus(400, 'Signature not set');
    }

    // is this request comes from LINE?
    if($_ENV['PASS_SIGNATURE'] == false && ! SignatureValidator::validateSignature($body, $_ENV['CHANNEL_SECRET'], $signature)){
        return $response->withStatus(400, 'Invalid signature');
    }
      
    $key_word = ["หวย", "เลข", "ดวง", "โชค", "ขอหวย", "เจ้าแม่ขอหวยหน่อย", "ขอเลขเด็ด"];
    $say_halo = ["สวัสดี", "ดีจ้า", "Hello", "Hi","Halo", "ว่าไง", "Hey"];
    $lotto = ["งวด", "ที่แล้ว", "ออกอะไร", "หวยงวดที่แล้วออกอะไร"];
    
    // init bot
    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
    $data = json_decode($body, true);
    foreach ($data['events'] as $event)
    {
        $userMessage = $event['message']['text'];
        if(in_array(strtolower($userMessage), $key_word))
        {
            $message = rand(000000, 999999);
            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
            $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
            return $result->getHTTPStatus() . ' ' . $result->getRawBody();

        } elseif (in_array(strtolower($userMessage), $lotto)) {
            $message = "เจ้าแม่ไม่รู้เลยจ้า :(";
            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
            $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
            return $result->getHTTPStatus() . ' ' . $result->getRawBody();

        } elseif(in_array(strtolower($userMessage), $say_halo)) {
            $message = "สวัสดีจ้า ขอหวยเจ้าแม่มาได้เลย";
            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
            $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
            return $result->getHTTPStatus() . ' ' . $result->getRawBody();

        } else {
            if (!empty($userMessage)) {
                sleep(10);

                define('LINE_API',"https://notify-api.line.me/api/notify");
                $token = $_ENV['NOTIFICATION_TOKEN'];
                $str = "User รอเกิน 10 วินาทีแล้ว กรุณาตรวจสอบ. ข้อความจากลูกค้า \"" .$userMessage. "\""; 

                $queryData = array('message' => $str);
                $queryData = http_build_query($queryData,'','&');
                $headerOptions = array( 
                        'http'=>array(
                           'method'=>'POST',
                           'header'=> "Content-Type: application/x-www-form-urlencoded\r\n"
                                     ."Authorization: Bearer ".$token."\r\n"
                                     ."Content-Length: ".strlen($queryData)."\r\n",
                           'content' => $queryData
                     ),
                );
                $context = stream_context_create($headerOptions);
                $result = file_get_contents(LINE_API,FALSE,$context);
                $res = json_decode($result);
                print_r($res);
            } 
        }
    }
});

/* JUST RUN IT */
$app->run();