<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class FormSubmissionService
{
    public function __construct(private PDO $db, private string $rateLimitSecret, private int $maxAttempts=5, private int $windowSeconds=600) {}

    public function submit(string $key,string $locale,array $input,string $remoteAddress): ?int
    {
        $form=(new FormManager($this->db))->findByKey($key,true);
        if(!$form)throw new RuntimeException('This form is not available.');
        if(trim((string)($input['_website']??''))!=='')return null;
        if($this->rateLimitSecret===''||$remoteAddress==='')throw new RuntimeException('Form submission could not be verified.');
        $ipHash=hash_hmac('sha256',$remoteAddress,$this->rateLimitSecret);
        $driver=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $window=max(1,min(86400,$this->windowSeconds));
        $cleanup=$driver==='sqlite'
            ? $this->db->prepare("DELETE FROM core_form_rate_limits WHERE created_at < datetime('now','-1 day')")
            : $this->db->prepare('DELETE FROM core_form_rate_limits WHERE created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)');
        $cleanup->execute();

        if(!$this->db->beginTransaction())throw new RuntimeException('Form rate limit transaction could not be started.');
        try{
            // Lock the form row so simultaneous requests for the same form cannot
            // all pass the count check before any of them records an attempt.
            if($driver==='sqlite'){
                $lock=$this->db->prepare('UPDATE core_forms SET id=id WHERE id=?');
                $lock->execute([(int)$form['id']]);
                $exists=$this->db->prepare('SELECT id FROM core_forms WHERE id=? LIMIT 1');
                $exists->execute([(int)$form['id']]);
                if(!$exists->fetchColumn())throw new RuntimeException('This form is not available.');
                $count=$this->db->prepare("SELECT COUNT(*) FROM core_form_rate_limits WHERE form_id=? AND ip_hash=? AND created_at>=datetime('now','-".$window." seconds')");
            }else{
                $lock=$this->db->prepare('SELECT id FROM core_forms WHERE id=? FOR UPDATE');
                $lock->execute([(int)$form['id']]);
                if(!$lock->fetchColumn())throw new RuntimeException('This form is not available.');
                $count=$this->db->prepare('SELECT COUNT(*) FROM core_form_rate_limits WHERE form_id=? AND ip_hash=? AND created_at>=DATE_SUB(CURRENT_TIMESTAMP, INTERVAL '.$window.' SECOND)');
            }
            $count->execute([(int)$form['id'],$ipHash]);
            if((int)$count->fetchColumn()>=$this->maxAttempts)throw new RuntimeException('Too many submissions. Please wait a few minutes and try again.');
            $insert=$this->db->prepare('INSERT INTO core_form_rate_limits(form_id,ip_hash) VALUES(?,?)');
            $insert->execute([(int)$form['id'],$ipHash]);
            $this->db->commit();
        }catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
        return (new FormManager($this->db))->submit($key,$locale,$input);
    }
}
