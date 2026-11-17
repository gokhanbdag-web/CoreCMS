<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;

final class FormNotificationService
{
    public function __construct(private PDO $db,private MailQueue $queue,private EmailTemplateManager $templates) {}

    public function queueSubmission(array $form,int $submissionId,array $input,string $locale,string $siteName):?int
    {
        $recipient=trim((string)($form['recipient_email']??''));
        if($recipient==='')return null;
        $escape=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $localeManager=new LocaleManager($this->db);
        $text=static fn(string $key,string $fallback):string=>$localeManager->translate($key,$locale,$fallback);
        $rows=[];$plain=[];
        foreach(($form['fields']??[]) as $field){
            $key=(string)($field['key']??'');$value=$input[$key]??'';
            if(($field['type']??'')==='checkbox')$value=in_array($value,[true,1,'1','on','yes'],true)?$text('frontend.forms.yes','Yes'):$text('frontend.forms.no','No');
            if(is_array($value)||is_object($value))continue;
            $value=trim((string)$value);
            $label=(string)($field['label']??$key);
            $rows[]='<tr><th style="text-align:left;padding:8px;border-bottom:1px solid #eee">'.$escape($label).'</th><td style="padding:8px;border-bottom:1px solid #eee">'.$escape($value).'</td></tr>';
            $plain[]=$label.': '.$value;
        }
        $variables=['form_name'=>(string)($form['name']??''),'submission_id'=>(string)$submissionId,'site_name'=>$siteName,'submission_data'=>implode("\n",$plain)];
        $template=$this->templates->render('forms.submission_notification',$locale,$variables);
        if($template){
            $subject=(string)$template['subject'];$body=(string)$template['body'];
        }else{
            $formName=(string)($form['name']??'Form');
            $subject=str_replace('{form_name}',$formName,$text('frontend.forms.notification_subject','New form submission: {form_name}'));
            $body='<h2>'.$escape($text('frontend.forms.notification_heading','New form submission')).'</h2><p>'. $escape($formName) .' · #'.(int)$submissionId.'</p><table cellpadding="0" cellspacing="0" style="border-collapse:collapse">'.implode('',$rows).'</table><p>'.$escape($siteName).'</p>';
        }
        return $this->queue->enqueue($recipient,$subject,$body);
    }
}
