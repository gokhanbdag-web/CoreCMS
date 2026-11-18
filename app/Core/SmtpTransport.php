<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use RuntimeException;

final class SmtpTransport
{
    public static function validateSettings(array $settings): void
    {
        $host=trim((string)($settings['smtp_host']??''));
        $port=(int)($settings['smtp_port']??0);
        $security=(string)($settings['smtp_security']??'starttls');
        $from=trim((string)($settings['mail_from_email']??''));
        if($host===''||preg_match('/[\r\n\s\/\\\\]/',$host))throw new RuntimeException('SMTP server address is invalid.');
        if($port<1||$port>65535)throw new RuntimeException('SMTP port is invalid.');
        if(!in_array($security,['none','starttls','ssl'],true))throw new RuntimeException('SMTP security mode is invalid.');
        if(!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('A valid sender email address is required for SMTP.');
        foreach(['smtp_username','smtp_password'] as $key){
            if(preg_match('/[\r\n]/',(string)($settings[$key]??'')))throw new RuntimeException('SMTP credentials contain invalid characters.');
        }
    }

    public function send(string $to,string $subject,string $html,array $settings): bool
    {
        self::validateSettings($settings);
        $host=trim((string)$settings['smtp_host']);
        $port=(int)$settings['smtp_port'];
        $security=(string)($settings['smtp_security']??'starttls');
        $timeout=max(5,min(60,(int)($settings['smtp_timeout']??15)));
        $remote=($security==='ssl'?'ssl://':'tcp://').$host.':'.$port;
        $context=stream_context_create(['ssl'=>[
            'verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>$host
        ]]);
        $errno=0;$error='';
        $stream=@stream_socket_client($remote,$errno,$error,$timeout,STREAM_CLIENT_CONNECT,$context);
        if(!is_resource($stream))throw new RuntimeException('SMTP connection failed: '.$error.' ('.$errno.').');
        stream_set_timeout($stream,$timeout);
        try{
            $this->expect($stream,[220]);
            $serverName=(string)(parse_url((string)($settings['site_url']??''),PHP_URL_HOST)?:'localhost');
            if(preg_match('/[^A-Za-z0-9.-]/',$serverName))$serverName='localhost';
            $this->command($stream,'EHLO '.$serverName,[250]);
            if($security==='starttls'){
                $this->command($stream,'STARTTLS',[220]);
                $crypto=stream_socket_enable_crypto($stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if($crypto!==true)throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                $this->command($stream,'EHLO '.$serverName,[250]);
            }
            $username=(string)($settings['smtp_username']??'');
            $password=(string)($settings['smtp_password']??'');
            if($username!==''){
                if($password==='')throw new RuntimeException('SMTP password is required when authentication is enabled.');
                $this->command($stream,'AUTH LOGIN',[334]);
                $this->command($stream,base64_encode($username),[334]);
                $this->command($stream,base64_encode($password),[235]);
            }
            $from=trim((string)$settings['mail_from_email']);
            $this->command($stream,'MAIL FROM:<'.$from.'>',[250]);
            $this->command($stream,'RCPT TO:<'.$to.'>',[250,251]);
            $this->command($stream,'DATA',[354]);
            $fromName=trim((string)($settings['mail_from_name']??''));
            $fromHeader=$fromName!==''?'=?UTF-8?B?'.base64_encode($fromName).'?= <'.$from.'>':$from;
            $messageHeaders=[
                'From: '.$fromHeader,
                'To: '.$to,
                'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'X-Mailer: CoreCMS'
            ];
            $body=str_replace(["\r\n","\r"],"\n",$html);
            $body=str_replace("\n","\r\n",$body);
            $body=preg_replace('/(^|\r\n)\./','$1..',$body)??$body;
            $message=implode("\r\n",$messageHeaders)."\r\n\r\n".$body."\r\n.";
            $this->write($stream,$message."\r\n");
            $this->expect($stream,[250]);
            try{$this->command($stream,'QUIT',[221]);}catch(\Throwable){}
            return true;
        }finally{
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private function command($stream,string $command,array $expected):string
    {
        if(preg_match('/[\r\n]/',$command))throw new RuntimeException('Invalid SMTP command.');
        $this->write($stream,$command."\r\n");
        return $this->expect($stream,$expected);
    }

    /** @param resource $stream */
    private function write($stream,string $data):void
    {
        $length=strlen($data);$written=0;
        while($written<$length){
            $count=@fwrite($stream,substr($data,$written));
            if($count===false||$count===0)throw new RuntimeException('SMTP connection closed while writing.');
            $written+=$count;
        }
    }

    /** @param resource $stream */
    private function expect($stream,array $expected):string
    {
        $response='';$code=0;
        for($lineCount=0;$lineCount<100;$lineCount++){
            $line=fgets($stream,8192);
            if($line===false){
                $meta=stream_get_meta_data($stream);
                throw new RuntimeException(!empty($meta['timed_out'])?'SMTP server response timed out.':'SMTP server closed the connection unexpectedly.');
            }
            $response.=$line;
            if(preg_match('/^(\d{3})([ -])/',rtrim($line,"\r\n"),$match)){
                $code=(int)$match[1];
                if($match[2]===' ')break;
            }
        }
        if(!in_array($code,$expected,true)){
            $safe=preg_replace('/[\r\n]+/',' ',trim($response))??'Unknown SMTP response';
            throw new RuntimeException('SMTP server rejected a command ('.$code.'): '.substr($safe,0,300));
        }
        return $response;
    }
}
