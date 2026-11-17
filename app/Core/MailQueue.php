<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use Closure;
use PDO;
use RuntimeException;

final class MailQueue
{
    private Closure $sender;

    public function __construct(private PDO $db, ?callable $sender=null)
    {
        $this->sender=$sender!==null?Closure::fromCallable($sender):static function(string $to,string $subject,string $body,string $headers):bool {
            if(!function_exists('mail'))return false;
            return mail($to,$subject,$body,$headers);
        };
    }

    public function enqueue(string $recipient,string $subject,string $body):int
    {
        $recipient=trim($recipient);
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Mail recipient address is invalid.');
        if($subject===''||strlen($subject)>500||preg_match('/[\r\n]/',$subject))throw new RuntimeException('Mail subject is invalid.');
        if(trim($body)===''||strlen($body)>100000)throw new RuntimeException('Mail body is empty or too long.');
        $stmt=$this->db->prepare("INSERT INTO core_mail_queue(recipient,subject,body,status,attempts,next_attempt_at) VALUES(?,?,?,'pending',0,CURRENT_TIMESTAMP)");
        $stmt->execute([$recipient,$subject,$body]);
        return (int)$this->db->lastInsertId();
    }

    public function process(array $settings,int $limit=5):array
    {
        $from=trim((string)($settings['mail_from_email']??''));
        if(!filter_var($from,FILTER_VALIDATE_EMAIL))return ['sent'=>0,'failed'=>0,'skipped'=>true];
        $limit=max(1,min(25,$limit));
        $stmt=$this->db->query("SELECT * FROM core_mail_queue WHERE status='pending' AND (next_attempt_at IS NULL OR next_attempt_at<=CURRENT_TIMESTAMP) ORDER BY id ASC LIMIT ".$limit);
        $sent=0;$failed=0;
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $subject='=?UTF-8?B?'.base64_encode((string)$row['subject']).'?=';
            $headers="From: ".$from."\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nX-Mailer: CoreCMS";
            $ok=false;
            try{$ok=($this->sender)((string)$row['recipient'],$subject,(string)$row['body'],$headers);}catch(\Throwable $e){error_log('[CoreCMS] Mail transport failed: '.$e->getMessage());}
            $attempts=(int)$row['attempts']+1;
            if($ok){
                $update=$this->db->prepare("UPDATE core_mail_queue SET status='sent',attempts=?,sent_at=CURRENT_TIMESTAMP,last_error=NULL WHERE id=?");
                $update->execute([$attempts,(int)$row['id']]);$sent++;continue;
            }
            $delays=[60,300,900,3600,21600];
            $status=$attempts>=5?'failed':'pending';
            $next=date('Y-m-d H:i:s',time()+$delays[min($attempts-1,4)]);
            $update=$this->db->prepare('UPDATE core_mail_queue SET status=?,attempts=?,next_attempt_at=?,last_error=? WHERE id=?');
            $update->execute([$status,$attempts,$next,'Mail transport did not accept the message. '.($attempts>=5?'Manual retry is required.':'A retry has been scheduled.'),(int)$row['id']]);
            $failed++;
        }
        return ['sent'=>$sent,'failed'=>$failed,'skipped'=>false];
    }

    public function retry(int $id):void
    {
        $stmt=$this->db->prepare("UPDATE core_mail_queue SET status='pending',attempts=0,next_attempt_at=CURRENT_TIMESTAMP,last_error=NULL WHERE id=? AND status IN ('failed','pending')");
        $stmt->execute([$id]);
        if($stmt->rowCount()<1){$check=$this->db->prepare('SELECT id FROM core_mail_queue WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())throw new RuntimeException('Mail queue item was not found.');}
    }

    public function recent(int $limit=50):array
    {
        $limit=max(1,min(200,$limit));
        return $this->db->query('SELECT id,recipient,subject,status,attempts,next_attempt_at,sent_at,last_error,created_at FROM core_mail_queue ORDER BY id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);
    }
}
