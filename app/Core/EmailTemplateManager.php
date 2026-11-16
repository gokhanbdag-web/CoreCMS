<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class EmailTemplateManager
{
    public function __construct(private PDO $db) {}

    public function templates(): array
    {
        return $this->db->query('SELECT * FROM core_email_templates ORDER BY template_key')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(string $key, string $locale): ?array
    {
        $stmt=$this->db->prepare('SELECT t.template_key,t.name,COALESCE(tr.subject,t.subject) AS subject,COALESCE(tr.body,t.body) AS body,CASE WHEN tr.locale_code IS NULL THEN 0 ELSE 1 END AS translated FROM core_email_templates t LEFT JOIN core_email_template_translations tr ON tr.template_key=t.template_key AND tr.locale_code=? WHERE t.template_key=? LIMIT 1');
        $stmt->execute([$locale,$key]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }

    public function save(string $key,string $name,string $subject,string $body): void
    {
        $key=strtolower(trim($key));$name=trim($name);$subject=trim($subject);
        if(!preg_match('/^[a-z][a-z0-9_.-]{1,99}$/',$key))throw new RuntimeException('Email template key is invalid.');
        if($name===''||mb_strlen($name,'UTF-8')>190)throw new RuntimeException('Email template name is required and cannot exceed 190 characters.');
        if($subject===''||mb_strlen($subject,'UTF-8')>255)throw new RuntimeException('Email subject is required and cannot exceed 255 characters.');
        if(trim($body)===''||mb_strlen($body,'UTF-8')>50000)throw new RuntimeException('Email template body is required and cannot exceed 50,000 characters.');
        $driver=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql=$driver==='sqlite'
            ? 'INSERT INTO core_email_templates(template_key,name,subject,body) VALUES(?,?,?,?) ON CONFLICT(template_key) DO UPDATE SET name=excluded.name,subject=excluded.subject,body=excluded.body,updated_at=CURRENT_TIMESTAMP'
            : 'INSERT INTO core_email_templates(template_key,name,subject,body) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),subject=VALUES(subject),body=VALUES(body),updated_at=CURRENT_TIMESTAMP';
        $this->db->prepare($sql)->execute([$key,$name,$subject,$body]);
    }

    public function saveTranslation(string $key,string $locale,string $subject,string $body): void
    {
        $template=$this->db->prepare('SELECT template_key FROM core_email_templates WHERE template_key=?');
        $template->execute([$key]);
        if(!$template->fetchColumn())throw new RuntimeException('Email template was not found.');
        $language=$this->db->prepare('SELECT enabled,is_default FROM core_languages WHERE locale_code=?');
        $language->execute([$locale]);$row=$language->fetch(PDO::FETCH_ASSOC);
        if(!$row||(int)$row['enabled']!==1)throw new RuntimeException('Email template language must be enabled.');
        if((int)$row['is_default']===1)throw new RuntimeException('Edit the base email template directly for the default language.');
        $subject=trim($subject);
        if($subject===''||mb_strlen($subject,'UTF-8')>255)throw new RuntimeException('Email subject is required and cannot exceed 255 characters.');
        if(trim($body)===''||mb_strlen($body,'UTF-8')>50000)throw new RuntimeException('Email template body is required and cannot exceed 50,000 characters.');
        $driver=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql=$driver==='sqlite'
            ? 'INSERT INTO core_email_template_translations(template_key,locale_code,subject,body) VALUES(?,?,?,?) ON CONFLICT(template_key,locale_code) DO UPDATE SET subject=excluded.subject,body=excluded.body,updated_at=CURRENT_TIMESTAMP'
            : 'INSERT INTO core_email_template_translations(template_key,locale_code,subject,body) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE subject=VALUES(subject),body=VALUES(body),updated_at=CURRENT_TIMESTAMP';
        $this->db->prepare($sql)->execute([$key,$locale,$subject,$body]);
    }

    public function render(string $key,string $locale,array $variables=[]): ?array
    {
        $template=$this->get($key,$locale);
        if(!$template)return null;
        $replacements=[];
        foreach($variables as $name=>$value){
            if(!is_string($name)||!preg_match('/^[a-z][a-z0-9_]{0,49}$/',$name)||!is_scalar($value))continue;
            $replacements['{{'.$name.'}}']=htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        }
        $template['subject']=strtr($template['subject'],$replacements);
        $template['body']=strtr($template['body'],$replacements);
        return $template;
    }
}
