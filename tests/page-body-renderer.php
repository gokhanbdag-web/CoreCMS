<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/MediaLibrary.php';
require dirname(__DIR__).'/app/Core/PageBodyRenderer.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_media(id INTEGER PRIMARY KEY,file_path TEXT,alt_text TEXT)");
$db->exec("INSERT INTO core_media VALUES(7,'uploads/media/".str_repeat('a',40).".jpg','Manzara')");
$media=new CoreCMS\Core\MediaLibrary($db,dirname(__DIR__),'/cms');
$source='# Başlık'."\n\n".'**Kalın** ve *eğik* metin'."\n\n".'- Birinci'."\n".'- İkinci'."\n\n".'[[media:7|50|right]]'."\n\n".'[Güvenli bağlantı](https://example.com/yol)'."\n\n".'[Zararlı](javascript:alert(1))'."\n\n".'&lt;script&gt;alert(1)&lt;/script&gt;';
$render=CoreCMS\Core\PageBodyRenderer::render($db,$source,$media);
foreach(['<h2>Başlık</h2>','<strong>Kalın</strong>','<em>eğik</em>','<ul>','<li>Birinci</li>','content-image-50 content-image-right','href="https://example.com/yol"','/cms/uploads/media/'.str_repeat('a',40).'.jpg'] as $expected)if(!str_contains($render,$expected))throw new RuntimeException('Missing expected output: '.$expected);
if(str_contains($render,'<script>')||str_contains($render,'href="javascript:')||str_contains($render,'media:999'))throw new RuntimeException('Unsafe or unresolved content rendered.');
echo 'Rich page renderer checks passed.'.PHP_EOL;
