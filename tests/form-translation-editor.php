<?php
declare(strict_types=1);

$admin=(string)file_get_contents(dirname(__DIR__).'/admin/index.php');
$checks=[
    'visual translation form is rendered'=>str_contains($admin,'form-translation-editor'),
    'form name is a separate input'=>str_contains($admin,'translations[_form][name]'),
    'form description is a separate textarea'=>str_contains($admin,'translations[_form][description]'),
    'success message is a separate input'=>str_contains($admin,'translations[_form][success_message]'),
    'field labels are individual inputs'=>str_contains($admin,'[label]'),
    'select option translations use stable original values'=>str_contains($admin,'option_values['),
    'JSON translation textarea has been removed'=>!str_contains($admin,'name="translations_json"'),
    'structured translation submission is handled'=>str_contains($admin,'$translations=$_POST[\'translations\']??[];'),
];
foreach($checks as $name=>$passed){if(!$passed)throw new RuntimeException('FAIL: '.$name);}
echo 'Visual form translation editor checks passed ('.count($checks).').'.PHP_EOL;
