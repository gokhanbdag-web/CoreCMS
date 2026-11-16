<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/MediaLibrary.php';
require dirname(__DIR__).'/app/Core/PageBodyRenderer.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_media(id INTEGER PRIMARY KEY,file_path TEXT,alt_text TEXT)");
$db->exec("INSERT INTO core_media VALUES(7,'uploads/media/".str_repeat('a',40).".jpg','Güzel <manzara>')");
$media=new CoreCMS\Core\MediaLibrary($db,dirname(__DIR__),'/cms');
$render=CoreCMS\Core\PageBodyRenderer::render($db,'Merhaba &lt;script&gt;alert(1)&lt;/script&gt; [[media:7]] [[media:999]]',$media);
if(str_contains($render,'<script>')) throw new RuntimeException('Untrusted HTML was rendered.');
if(!str_contains($render,'/cms/uploads/media/'.str_repeat('a',40).'.jpg')) throw new RuntimeException('Valid media token was not rendered.');
if(!str_contains($render,'Güzel &lt;manzara&gt;')) throw new RuntimeException('Alt text was not escaped.');
if(str_contains($render,'media:999')) throw new RuntimeException('Unknown media token should not be exposed.');
echo 'Page body renderer checks passed.'.PHP_EOL;
