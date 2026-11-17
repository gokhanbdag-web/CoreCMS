<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;

final class FormRenderer
{
    public function __construct(
        private PDO $db,
        private string $locale,
        private string $csrfToken,
        private string $returnPath,
        private string $submitLabel='Gönder'
    ) {}

    public function render(string $key): string
    {
        $form=(new FormManager($this->db))->findByKey($key,true);
        if(!$form)return '';
        $escape=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $feedback=$_SESSION['corecms_form_feedback'][$key]??null;
        unset($_SESSION['corecms_form_feedback'][$key]);
        $html='<section class="corecms-form" id="form-'.$escape($key).'">';
        if(is_array($feedback)&&!empty($feedback['success']))$html.='<p class="success" role="status">'.$escape($feedback['message']??'Your message has been received.').'</p>';
        elseif(is_array($feedback)&&!empty($feedback['error']))$html.='<p class="error" role="alert">'.$escape($feedback['message']??'Please check the form and try again.').'</p>';
        if(!empty($form['description']))$html.='<p class="form-description">'.$escape($form['description']).'</p>';
        $html.='<form method="post" action="'.$escape($this->returnPath).'">';
        $html.='<input type="hidden" name="_csrf" value="'.$escape($this->csrfToken).'">';
        $html.='<input type="hidden" name="_corecms_form" value="'.$escape($key).'">';
        $html.='<input type="hidden" name="_corecms_locale" value="'.$escape($this->locale).'">';
        $html.='<input type="hidden" name="_corecms_return" value="'.$escape($this->returnPath).'">';
        $html.='<div class="corecms-form-trap" aria-hidden="true"><label>Leave this field empty<input type="text" name="_website" value="" tabindex="-1" autocomplete="off"></label></div>';
        foreach($form['fields'] as $field){
            $name=(string)$field['key'];$label=$escape($field['label']);$required=!empty($field['required']);
            $requiredAttr=$required?' required':'';
            $html.='<div class="form-field"><label for="corecms-'.$escape($key).'-'.$escape($name).'">'.$label.($required?' <span aria-hidden="true">*</span>':'').'</label>';
            $id='corecms-'.$key.'-'.$name;
            if($field['type']==='textarea')$html.='<textarea id="'.$escape($id).'" name="'.$escape($name).'" maxlength="10000"'.$requiredAttr.'></textarea>';
            elseif($field['type']==='select'){
                $html.='<select id="'.$escape($id).'" name="'.$escape($name).'"'.$requiredAttr.'><option value="">—</option>';
                foreach($field['options'] as $option)$html.='<option value="'.$escape($option).'">'.$escape($option).'</option>';
                $html.='</select>';
            }elseif($field['type']==='checkbox'){
                $html.='<input id="'.$escape($id).'" type="checkbox" name="'.$escape($name).'" value="1"'.($required?' required':'').'>';
            }else{
                $type=in_array($field['type'],['email','tel'],true)?$field['type']:'text';
                $html.='<input id="'.$escape($id).'" type="'.$type.'" name="'.$escape($name).'" maxlength="1000"'.$requiredAttr.'>';
            }
            $html.='</div>';
        }
        $html.='<button type="submit">'.$escape($this->submitLabel).'</button></form></section>';
        return $html;
    }
}
