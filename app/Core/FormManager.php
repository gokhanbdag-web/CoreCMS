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

    public function findByKey(string $key, bool $enabledOnly = true, ?string $locale = null): ?array
    {
        $sql='SELECT * FROM core_forms WHERE form_key=?'.($enabledOnly?' AND enabled=1':'').' LIMIT 1';
        $stmt=$this->db->prepare($sql);
        $stmt->execute([$key]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))return null;
        $row['fields']=$this->decodeFields((string)$row['fields_json']);
        if ($locale !== null) {
            $translations=$this->fieldTranslations((int)$row['id'],$locale);
            $metadata=$translations['_form']??[];
            if(is_array($metadata)){
                foreach(['name','description','success_message'] as $property){
                    if(isset($metadata[$property])&&is_string($metadata[$property]))$row[$property]=$metadata[$property];
                }
            }
            $row['fields']=$this->applyFieldTranslations((int)$row['id'],$row['fields'],$locale);
        }
        return $row;
    }

    public function fieldTranslations(int $formId, string $locale): array
    {
        $stmt=$this->db->prepare('SELECT translations_json FROM core_form_field_translations WHERE form_id=? AND locale_code=? LIMIT 1');
        $stmt->execute([$formId,$locale]);
        $json=$stmt->fetchColumn();
        if(!is_string($json)||$json==='')return [];
        $value=json_decode($json,true);
        return is_array($value)?$value:[];
    }

    public function saveFieldTranslations(int $formId, string $locale, array $translations): void
    {
        $form=$this->findById($formId);
        if(!$form)throw new RuntimeException('Form was not found.');
        $language=$this->db->prepare('SELECT enabled,is_default FROM core_languages WHERE locale_code=?');
        $language->execute([$locale]);
        $languageRow=$language->fetch(PDO::FETCH_ASSOC);
        if(!$languageRow||(int)$languageRow['enabled']!==1)throw new RuntimeException('Form translation language must be enabled.');
        if((int)$languageRow['is_default']===1)throw new RuntimeException('The default language uses the base form labels.');
        $allowed=[];
        foreach($form['fields'] as $field)$allowed[$field['key']]=$field;
        $normalized=[];
        foreach($translations as $fieldKey=>$translation){
            if($fieldKey==='_form'){
                if(!is_array($translation))throw new RuntimeException('Form metadata translation must be an object.');
                $metadata=[];
                foreach(['name'=>190,'description'=>1000,'success_message'=>500] as $property=>$maxLength){
                    if(!array_key_exists($property,$translation))continue;
                    $value=trim((string)$translation[$property]);
                    if(($property==='name'&&$value==='')||self::length($value,'UTF-8')>$maxLength)throw new RuntimeException('Translated form '.$property.' is empty or too long.');
                    $metadata[$property]=$value;
                }
                if($metadata)$normalized['_form']=$metadata;
                continue;
            }
            if(!is_string($fieldKey)||!isset($allowed[$fieldKey])||!is_array($translation))throw new RuntimeException('Unknown form field translation.');
            $entry=[];
            if(array_key_exists('label',$translation)){
                $label=trim((string)$translation['label']);
                if($label===''||self::length($label,'UTF-8')>120)throw new RuntimeException('Translated field labels must be between 1 and 120 characters.');
                $entry['label']=$label;
            }
            if(array_key_exists('options',$translation)){
                if($allowed[$fieldKey]['type']!=='select'||!is_array($translation['options']))throw new RuntimeException('Only select fields can have translated options.');
                $options=[];
                foreach($translation['options'] as $original=>$translated){
                    if(!is_string($original)||!in_array($original,$allowed[$fieldKey]['options'],true))throw new RuntimeException('Unknown select option translation.');
                    $translated=trim((string)$translated);
                    if($translated===''||self::length($translated,'UTF-8')>120)throw new RuntimeException('Translated options must be between 1 and 120 characters.');
                    $options[$original]=$translated;
                }
                $entry['options']=$options;
            }
            if($entry)$normalized[$fieldKey]=$entry;
        }
        $json=json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $driver=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql=$driver==='sqlite'
            ? 'INSERT INTO core_form_field_translations(form_id,locale_code,translations_json) VALUES(?,?,?) ON CONFLICT(form_id,locale_code) DO UPDATE SET translations_json=excluded.translations_json,updated_at=CURRENT_TIMESTAMP'
            : 'INSERT INTO core_form_field_translations(form_id,locale_code,translations_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translations_json=VALUES(translations_json),updated_at=CURRENT_TIMESTAMP';
        $this->db->prepare($sql)->execute([$formId,$locale,$json]);
    }

    private function applyFieldTranslations(int $formId,array $fields,string $locale): array
    {
        $translations=$this->fieldTranslations($formId,$locale);
        foreach($fields as &$field){
            $translation=$translations[$field['key']]??null;
            if(!is_array($translation))continue;
            if(isset($translation['label'])&&is_string($translation['label']))$field['label']=$translation['label'];
            if($field['type']==='select'&&isset($translation['options'])&&is_array($translation['options'])){
                $field['option_labels']=[];
                foreach($field['options'] as $option)$field['option_labels'][$option]=(string)($translation['options'][$option]??$option);
            }
        }
        unset($field);
        return $fields;
    }

    public function save(?int $id, string $key, string $name, array $fields, string $recipientEmail = '', string $description = '', string $successMessage = '', bool $enabled = true): int
    {
        $key=strtolower(trim($key));
        $name=trim($name);
        $recipientEmail=trim($recipientEmail);
        $description=trim($description);
        $successMessage=trim($successMessage);
        if(!preg_match('/^[a-z][a-z0-9_-]{1,99}$/',$key))throw new RuntimeException('Form key must be 2–100 lowercase letters, numbers, hyphens or underscores.');
        if($name===''||self::length($name,'UTF-8')>190)throw new RuntimeException('Form name is required and cannot exceed 190 characters.');
        if($recipientEmail!==''&&!filter_var($recipientEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Notification email address is invalid.');
        if(self::length($description,'UTF-8')>1000||self::length($successMessage,'UTF-8')>500)throw new RuntimeException('Form description or success message is too long.');
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
                if($field['required']&&!$value)throw new RuntimeException('Required field is missing: '.$fieldKey);
            }else{
                if(is_array($value)||is_object($value))throw new RuntimeException('Invalid value for field: '.$fieldKey);
                $value=trim((string)($value??''));
                if($field['required']&&$value==='')throw new RuntimeException('Required field is missing: '.$fieldKey);
                if($field['type']==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email address for field: '.$fieldKey);
                if($field['type']==='select'&&$value!==''&&!in_array($value,$field['options'],true))throw new RuntimeException('Invalid selection for field: '.$fieldKey);
                $max=$field['type']==='textarea'?10000:1000;
                if(self::length($value,'UTF-8')>$max)throw new RuntimeException('Field value is too long: '.$fieldKey);
            }
            $payload[$fieldKey]=$value;
        }
        $stmt=$this->db->prepare('INSERT INTO core_form_submissions(form_id,locale_code,payload_json,status) VALUES(?,?,?,?)');
        $stmt->execute([(int)$form['id'],$locale,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'new']);
        return (int)$this->db->lastInsertId();
    }

    public function submissions(?int $formId = null, int $limit = 100, array $filters = []): array
    {
        $limit=max(1,min(500,$limit));
        $where=[];$params=[];
        if($formId!==null&&$formId>0){$where[]='s.form_id=?';$params[]=$formId;}
        $status=(string)($filters['status']??'');
        if(in_array($status,['new','read','archived'],true)){$where[]='s.status=?';$params[]=$status;}
        $locale=trim((string)($filters['locale']??''));
        if($locale!==''&&preg_match('/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,8})?$/',$locale)){$where[]='s.locale_code=?';$params[]=$locale;}
        $from=(string)($filters['from']??'');
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='s.created_at>=?';$params[]=$from.' 00:00:00';}
        $to=(string)($filters['to']??'');
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='s.created_at<=?';$params[]=$to.' 23:59:59';}
        $search=trim((string)($filters['search']??''));
        if($search!==''){
            $where[]='(f.name LIKE ? OR f.form_key LIKE ? OR s.payload_json LIKE ?)';
            $like='%'.$search.'%';array_push($params,$like,$like,$like);
        }
        $sql='SELECT s.*,f.form_key,f.name AS form_name FROM core_form_submissions s INNER JOIN core_forms f ON f.id=s.form_id';
        if($where)$sql.=' WHERE '.implode(' AND ',$where);
        $sql.=' ORDER BY s.id DESC LIMIT '.$limit;
        $stmt=$this->db->prepare($sql);$stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row)$row['payload']=$this->decodePayload((string)$row['payload_json']);
        unset($row);
        return $rows;
    }

    public function updateSubmissionStatus(int $id, string $status): void
    {
        if(!in_array($status,['new','read','archived'],true))throw new RuntimeException('Invalid submission status.');
        $stmt=$this->db->prepare('UPDATE core_form_submissions SET status=? WHERE id=?');
        $stmt->execute([$status,$id]);
        if($stmt->rowCount()<1){$check=$this->db->prepare('SELECT id FROM core_form_submissions WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())throw new RuntimeException('Form submission was not found.');}
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
            if($label===''||self::length($label,'UTF-8')>120)throw new RuntimeException('Form field label is required and cannot exceed 120 characters.');
            $options=[];
            if($type==='select'){
                $raw=$field['options']??[];
                if(!is_array($raw)||count($raw)<1||count($raw)>50)throw new RuntimeException('Select fields need between 1 and 50 options.');
                foreach($raw as $option){if(!is_scalar($option))throw new RuntimeException('Invalid select option.');$option=trim((string)$option);if($option===''||self::length($option,'UTF-8')>120)throw new RuntimeException('Select options must be between 1 and 120 characters.');$options[]=$option;}
                if(count(array_unique($options))!==count($options))throw new RuntimeException('Select options must be unique.');
            }
            $seen[$key]=true;
            $normalized[]=['key'=>$key,'type'=>$type,'label'=>$label,'required'=>$required,'options'=>$options];
        }
        return $normalized;
    }

    private static function length(string $value): int
    {
        return preg_match_all('/./us', $value) ?: 0;
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
