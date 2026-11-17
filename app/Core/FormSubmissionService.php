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
        $cutoff=date('Y-m-d H:i:s',time()-$this->windowSeconds);
        $cleanup=$this->db->prepare('DELETE FROM core_form_rate_limits WHERE created_at < ?');
        $cleanup->execute([date('Y-m-d H:i:s',time()-86400)]);
        $count=$this->db->prepare('SELECT COUNT(*) FROM core_form_rate_limits WHERE form_id=? AND ip_hash=? AND created_at>=?');
        $count->execute([(int)$form['id'],$ipHash,$cutoff]);
        if((int)$count->fetchColumn()>=$this->maxAttempts)throw new RuntimeException('Too many submissions. Please wait a few minutes and try again.');
        $insert=$this->db->prepare('INSERT INTO core_form_rate_limits(form_id,ip_hash) VALUES(?,?)');
        $insert->execute([(int)$form['id'],$ipHash]);
        return (new FormManager($this->db))->submit($key,$locale,$input);
    }
}
