<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use CoreCMS\Core\FormFieldEditor;

$fields = FormFieldEditor::fromPost([
    ['key'=>'full_name','type'=>'text','label'=>'Full name','required'=>'1','options_text'=>''],
    ['key'=>'contact_method','type'=>'select','label'=>'Contact method','options_text'=>"Email\r\n Phone \n\nSMS"],
    ['key'=>'message','type'=>'textarea','label'=>'Message','options_text'=>''],
]);
$checks = [
    'field order is preserved' => array_column($fields, 'key') === ['full_name','contact_method','message'],
    'required checkbox is normalized' => $fields[0]['required'] === true && $fields[1]['required'] === false,
    'dropdown options are split and trimmed' => $fields[1]['options'] === ['Email','Phone','SMS'],
    'non-select fields have no options' => $fields[2]['options'] === [],
    'field types and labels are retained' => $fields[1]['type'] === 'select' && $fields[1]['label'] === 'Contact method',
];
foreach ($checks as $name => $passed) {
    if (!$passed) throw new RuntimeException('FAIL: '.$name);
}
try {
    FormFieldEditor::fromPost('invalid');
    throw new RuntimeException('FAIL: invalid payload was accepted');
} catch (RuntimeException $error) {
    if ($error->getMessage() === 'FAIL: invalid payload was accepted') throw $error;
}
echo 'Visual form builder checks passed ('.(count($checks)+1).').'.PHP_EOL;
