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
    return "Line Bot : เลขมงคล <br> Welcome!";
});

$app->post('/', function ($request, $response)
{
    $start_time = time();
    
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
    if($_ENV['PASS_SIGNATURE'] == false 
            && ! SignatureValidator::validateSignature($body, $_ENV['CHANNEL_SECRET'], $signature)){
        return $response->withStatus(400, 'Invalid signature');
    }
     
    $keyword = 'หวย เลข ดวง โชค หวย เด็ด ขอ ขอหวย เจ้าแม่ขอหวยหน่อย ขอเลขเด็ดเจ้าแม่ขอหวยหน่อย ขอเลขเด็ด';
    $hi = 'สวัสดี ดีจ้า Hello Hi Halo ว่าไง Hey ยินดี';
    $oh = 'งวดที่แล้ว ออกอะไร หวยงวดที่แล้วออกอะไร เลขออกอะไร เลขอะไร ซื้อเลขอะไรดี ?';
    
    // init bot
    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
    $data = json_decode($body, true);
    $pass = true;
    
    foreach ($data['events'] as $event)
    {
        $userMessage = $event['message']['text'];

        // escape special characters in the query
        $pattern = preg_quote($userMessage, '/');
        
        // finalise the regular expression, matching the whole line
        $pattern = "/^.*$pattern.*\$/m";
        
        // search, and store all matching occurences in $matches
        if (preg_match_all($pattern, $keyword, $matches)){
            $message = rand(000000, 999999);
        } elseif (preg_match_all($pattern, $hi, $matches)){
            $message = "สวัสดีจ้า ขอหวยเจ้าแม่มาได้เลย";
        } elseif (preg_match_all($pattern, $oh, $matches)){ 
            sleep(15); // sample for the case which query more 15 minutes
            $message = "เจ้าแม่มึนตึ๊บ ^^!";
        } else {
            if (!empty($userMessage)) {
                $pass = false;
                sleep(10);
            } 
        }
        
        $time_usage = time()-$start_time;
        
        if ($time_usage >= 10)
            $pass = false;
        
        if (!$pass)
        {
            define('LINE_API',"https://notify-api.line.me/api/notify");
            $token = $_ENV['NOTIFICATION_TOKEN'];
            $str = "ใช้เวลาทั้งหมด " .$time_usage. " วินาที. ข้อความจากลูกค้า \"" .$userMessage. "\""; 

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
        
        $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
        $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
        return $result->getHTTPStatus() . ' ' . $result->getRawBody();
    }
});

/* JUST RUN IT */
$app->run();