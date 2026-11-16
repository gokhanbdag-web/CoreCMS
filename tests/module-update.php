<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/ModuleManager.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO SQLite is required for module update tests.');
}

$root = sys_get_temp_dir() . '/corecms-module-updates-' . bin2hex(random_bytes(5));
mkdir($root,0700,true);
$dir=$root.'/sample';
mkdir($dir.'/migrations',0700,true);
mkdir($dir.'/hooks',0700,true);
$checks=0;
$assert=static function(bool $ok,string $message) use (&$checks):void {
    $checks++;
    if(!$ok) throw new RuntimeException('FAIL: '.$message);
};

$writeManifest=static function(string $version,bool $withHooks=true) use($dir):void {
    $data=['id'=>'sample','name'=>'Sample module','version'=>$version,'dependencies'=>[]];
    if($withHooks) $data['update_hooks']=[
        'before_update'=>'hooks/before-update.php',
        'after_update'=>'hooks/after-update.php',
        'before_rollback'=>'hooks/before-rollback.php',
        'after_rollback'=>'hooks/after-rollback.php',
    ];
    file_put_contents($dir.'/module.json',json_encode($data,JSON_THROW_ON_ERROR));
};

try {
    file_put_contents($dir.'/migrations/001_initial.php', "<?php\nreturn static function (PDO \$db): void {\n    \$db->exec('CREATE TABLE sample_data (value TEXT PRIMARY KEY)');\n    \$db->exec(\"INSERT INTO sample_data(value) VALUES ('base')\");\n};\n");
    foreach(['before-update','after-update','before-rollback','after-rollback'] as $hook) {
        $event=str_replace('-','_',$hook);
        $hookCode="<?php\nreturn static function (PDO \$db, array \$context, array \$module): void {\n    \$stmt=\$db->prepare('INSERT INTO module_hook_events(event_name) VALUES (?)');\n    \$stmt->execute(['".$event."']);\n};\n";
        file_put_contents($dir.'/hooks/'.$hook.'.php',$hookCode);
    }

    $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE core_modules(module_id TEXT PRIMARY KEY,version TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 0)');
    $db->exec('CREATE TABLE module_hook_events(event_name TEXT NOT NULL)');
    $manager=new CoreCMS\Core\ModuleManager($db,$root);

    $writeManifest('1.0.0');
    $manager->install('sample');
    $manager->enable('sample');
    $writeManifest('1.1.0');
    $blocked=false;
    try{$manager->update('sample');}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'devre dışı');}
    $assert($blocked,'updates require module to be disabled');
    $manager->disable('sample');
    file_put_contents($dir.'/migrations/002_reversible.php', "<?php\nreturn [\n    'up' => static function (PDO \$db): void { \$db->exec(\"INSERT INTO sample_data(value) VALUES ('v2')\"); },\n    'down' => static function (PDO \$db): void { \$db->exec(\"DELETE FROM sample_data WHERE value='v2'\"); },\n];\n");


    $applied=$manager->update('sample');
    $assert($applied===['002_reversible.php'],'reversible update migration applied');
    $assert($manager->status('sample')['installed_version']==='1.1.0','module version advanced');
    $assert($manager->rollbackStatus('sample')['available']===true,'reversible update exposes rollback');
    $assert((int)$db->query("SELECT COUNT(*) FROM sample_data WHERE value='v2'")->fetchColumn()===1,'update data applied');

    $rolled=$manager->rollback('sample');
    $assert($rolled['from_version']==='1.1.0' && $rolled['to_version']==='1.0.0','rollback restores previous version');
    $assert($manager->status('sample')['installed_version']==='1.0.0','installed version restored');
    $assert((int)$db->query("SELECT COUNT(*) FROM sample_data WHERE value='v2'")->fetchColumn()===0,'down migration reversed update data');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_module_migrations WHERE migration='002_reversible.php'")->fetchColumn()===0,'rolled back migration removed from applied ledger');
    $assert((int)$db->query("SELECT COUNT(*) FROM module_hook_events WHERE event_name IN ('before_update','after_update','before_rollback','after_rollback')")->fetchColumn()===4,'all update lifecycle hooks executed');

        file_put_contents($dir.'/migrations/003_irreversible.php', "<?php\nreturn static function (PDO \$db): void { \$db->exec(\"INSERT INTO sample_data(value) VALUES ('v3')\"); };\n");
    $writeManifest('1.2.0');
    $applied=$manager->update('sample');
    $assert($applied===['002_reversible.php','003_irreversible.php'],'subsequent update applies new and previously rolled back migrations');
    $assert($manager->rollbackStatus('sample')['available']===false,'legacy irreversible migration disables automatic rollback');
    $rollbackBlocked=false;
    try{$manager->rollback('sample');}catch(RuntimeException $e){$rollbackBlocked=str_contains($e->getMessage(),'down işlemi');}
    $assert($rollbackBlocked,'irreversible update rollback is blocked');

    echo 'Module update checks passed ('.$checks.').' . PHP_EOL;
} finally {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    rmdir($root);
}
