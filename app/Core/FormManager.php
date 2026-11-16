<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class FormManager
{
    private const FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox'];

    public function __construct(private PDO $db) {}

    public function forms(): array
    {
        return $this->db->query('SELECT * FROM core_forms ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM core_forms WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))return null;
        $row['fields']=$this->decodeFields((string)$row['fields_json']);
        return $row;
    }

    public function findByKey(string $key, bool $enabledOnly = true): ?array
    {
        $sql='SELECT * FROM core_forms WHERE form_key=?'.($enabledOnly?' AND enabled=1':'').' LIMIT 1';
        $stmt=$this->db->prepare($sql);
        $stmt->execute([$key]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))return null;
        $row['fields']=$this->decodeFields((string)$row['fields_json']);
        return $row;
    }

    public function save(?int $id, string $key, string $name, array $fields, string $recipientEmail = '', string $description = '', string $successMessage = '', bool $enabled = true): int
    {
        $key=strtolower(trim($key));
        $name=trim($name);
        $recipientEmail=trim($recipientEmail);
        $description=trim($description);
        $successMessage=trim($successMessage);
        if(!preg_match('/^[a-z][a-z0-9_-]{1,99}$/',$key))throw new RuntimeException('Form key must be 2–100 lowercase letters, numbers, hyphens or underscores.');
        if($name===''||mb_strlen($name,'UTF-8')>190)throw new RuntimeException('Form name is required and cannot exceed 190 characters.');
        if($recipientEmail!==''&&!filter_var($recipientEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Notification email address is invalid.');
        if(mb_strlen($description,'UTF-8')>1000||mb_strlen($successMessage,'UTF-8')>500)throw new RuntimeException('Form description or success message is too long.');
        $normalized=$this->normalizeFields($fields);
        $json=json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if($id!==null&&$id>0){
            $stmt=$this->db->prepare('UPDATE core_forms SET form_key=?,name=?,description=?,fields_json=?,recipient_email=?,success_message=?,enabled=? WHERE id=?');
            $stmt->execute([$key,$name,$description?:null,$json,$recipientEmail?:null,$successMessage?:null,$enabled?1:0,$id]);
            if($stmt->rowCount()===0&&!$this->findById($id))throw new RuntimeException('Form was not found.');
            return $id;
        }
        $stmt=$this->db->prepare('INSERT INTO core_forms(form_key,name,description,fields_json,recipient_email,success_message,enabled) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$key,$name,$description?:null,$json,$recipientEmail?:null,$successMessage?:null,$enabled?1:0]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt=$this->db->prepare('DELETE FROM core_forms WHERE id=?');
        $stmt->execute([$id]);
        if($stmt->rowCount()<1)throw new RuntimeException('Form was not found.');
    }

    public function submit(string $key, string $locale, array $input): int
    {
        $form=$this->findByKey($key,true);
        if(!$form)throw new RuntimeException('This form is not available.');
        $language=$this->db->prepare('SELECT enabled FROM core_languages WHERE locale_code=?');
        $language->execute([$locale]);
        if((int)$language->fetchColumn()!==1)throw new RuntimeException('Submission locale is not enabled.');
        $payload=[];
        foreach($form['fields'] as $field){
            $fieldKey=$field['key'];
            $value=$input[$fieldKey]??null;
            if($field['type']==='checkbox'){
                $value=in_array($value,[true,1,'1','on','yes'],true);
            }else{
                if(is_array($value)||is_object($value))throw new RuntimeException('Invalid value for field: '.$fieldKey);
                $value=trim((string)($value??''));
                if($field['required']&&$value==='')throw new RuntimeException('Required field is missing: '.$fieldKey);
                if($field['type']==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email address for field: '.$fieldKey);
                if($field['type']==='select'&&$value!==''&&!in_array($value,$field['options'],true))throw new RuntimeException('Invalid selection for field: '.$fieldKey);
                $max=$field['type']==='textarea'?10000:1000;
                if(mb_strlen($value,'UTF-8')>$max)throw new RuntimeException('Field value is too long: '.$fieldKey);
            }
            $payload[$fieldKey]=$value;
        }
        $stmt=$this->db->prepare('INSERT INTO core_form_submissions(form_id,locale_code,payload_json,status) VALUES(?,?,?,?)');
        $stmt->execute([(int)$form['id'],$locale,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'new']);
        return (int)$this->db->lastInsertId();
    }

    public function submissions(?int $formId = null, int $limit = 100): array
    {
        $limit=max(1,min(500,$limit));
        if($formId!==null){
            $stmt=$this->db->prepare('SELECT s.*,f.form_key,f.name AS form_name FROM core_form_submissions s INNER JOIN core_forms f ON f.id=s.form_id WHERE s.form_id=? ORDER BY s.id DESC LIMIT '.$limit);
            $stmt->execute([$formId]);
        }else{
            $stmt=$this->db->query('SELECT s.*,f.form_key,f.name AS form_name FROM core_form_submissions s INNER JOIN core_forms f ON f.id=s.form_id ORDER BY s.id DESC LIMIT '.$limit);
        }
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row)$row['payload']=$this->decodePayload((string)$row['payload_json']);
        return $rows;
    }

    public function updateSubmissionStatus(int $id, string $status): void
    {
        if(!in_array($status,['new','read','archived'],true))throw new RuntimeException('Invalid submission status.');
        $stmt=$this->db->prepare('UPDATE core_form_submissions SET status=? WHERE id=?');
        $stmt->execute([$status,$id]);
        if($stmt->rowCount()<1)throw new RuntimeException('Form submission was not found.');
    }

    private function normalizeFields(array $fields): array
    {
        if(count($fields)<1||count($fields)>30)throw new RuntimeException('A form must contain between 1 and 30 fields.');
        $normalized=[];$seen=[];
        foreach($fields as $field){
            if(!is_array($field))throw new RuntimeException('Invalid form field definition.');
            $key=strtolower(trim((string)($field['key']??'')));
            $type=(string)($field['type']??'text');
            $label=trim((string)($field['label']??''));
            $required=(bool)($field['required']??false);
            if(!preg_match('/^[a-z][a-z0-9_]{0,39}$/',$key)||isset($seen[$key]))throw new RuntimeException('Form field keys must be unique valid identifiers.');
            if(!in_array($type,self::FIELD_TYPES,true))throw new RuntimeException('Unsupported form field type.');
            if($label===''||mb_strlen($label,'UTF-8')>120)throw new RuntimeException('Form field label is required and cannot exceed 120 characters.');
            $options=[];
            if($type==='select'){
                $raw=$field['options']??[];
                if(!is_array($raw)||count($raw)<1||count($raw)>50)throw new RuntimeException('Select fields need between 1 and 50 options.');
                foreach($raw as $option){if(!is_scalar($option))throw new RuntimeException('Invalid select option.');$option=trim((string)$option);if($option===''||mb_strlen($option,'UTF-8')>120)throw new RuntimeException('Select options must be between 1 and 120 characters.');$options[]=$option;}
                if(count(array_unique($options))!==count($options))throw new RuntimeException('Select options must be unique.');
            }
            $seen[$key]=true;
            $normalized[]=['key'=>$key,'type'=>$type,'label'=>$label,'required'=>$required,'options'=>$options];
        }
        return $normalized;
    }

    private function decodeFields(string $json): array
    {
        $value=json_decode($json,true);
        return is_array($value)?$value:[];
    }

    private function decodePayload(string $json): array
    {
        $value=json_decode($json,true);
        return is_array($value)?$value:[];
    }
}
