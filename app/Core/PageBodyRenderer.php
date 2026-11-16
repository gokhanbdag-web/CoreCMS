<?php
declare(strict_types=1);
namespace CoreCMS\Core;
use PDO;
final class PageBodyRenderer {
 public static function render(PDO $db,string $storedBody,MediaLibrary $media):string {
  $body=html_entity_decode($storedBody,ENT_QUOTES|ENT_HTML5,'UTF-8');$mediaHtml=[];
  $body=preg_replace_callback('/\[\[media:([1-9][0-9]*)(?:\|(100|75|50|25)\|(left|center|right))?\]\]/',static function(array $m)use($db,$media,&$mediaHtml):string{
   $q=$db->prepare('SELECT file_path,alt_text FROM core_media WHERE id=? LIMIT 1');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return '';
   $url=$media->url((string)$r['file_path']);if($url==='')return '';
   $width=$m[2]??'100';$align=$m[3]??'center';$key='CORECMSMEDIA'.count($mediaHtml).'TOKEN';
   $mediaHtml[$key]='<img class="content-image content-image-'.$width.' content-image-'.$align.'" src="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'" alt="'.htmlspecialchars((string)$r['alt_text'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" loading="lazy">';
   return $key;
  },$body)??$body;
  $blocks=preg_split('/\R{2,}/u',trim($body))?:[];$html='';
  foreach($blocks as $block){$lines=preg_split('/\R/u',$block)?:[];$first=trim($lines[0]??'');if($first==='')continue;
   if(preg_match('/^(#{1,3})\s+(.+)$/u',$first,$m)){$level=strlen($m[1])+1;$html.='<h'.$level.'>'.self::inline($m[2],$mediaHtml).'</h'.$level.'>';}
   elseif(preg_match('/^>\s?(.*)$/u',$first,$m))$html.='<blockquote>'.self::inline(implode("\n",array_map(static fn($v)=>preg_replace('/^>\s?/u','',$v)??$v,$lines)),$mediaHtml).'</blockquote>';
   elseif(preg_match('/^-\s+/u',$first)){$html.='<ul>';foreach($lines as $line)if(preg_match('/^-\s+(.+)$/u',$line,$m))$html.='<li>'.self::inline($m[1],$mediaHtml).'</li>';$html.='</ul>';}
   else $html.='<p>'.self::inline(implode("\n",$lines),$mediaHtml).'</p>';
  }return $html;
 }
 private static function inline(string $value,array $mediaHtml):string {
  $tokens=[];$value=preg_replace_callback('/\[([^\]\r\n]+)\]\(([^)\s]+)\)/u',static function(array $m)use(&$tokens):string{
   $url=trim($m[2]);$safe=preg_match('#^https://[^/\s]+(?:/[^\s]*)?$#i',$url)||preg_match('#^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$#i',$url)||(str_starts_with($url,'/')&&!str_starts_with($url,'//')&&!preg_match('/[\x00-\x20]/',$url));
   if(!$safe)return $m[0];$key='CORECMSLINK'.count($tokens).'TOKEN';$tokens[$key]='<a href="'.htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" rel="nofollow noopener">'.htmlspecialchars($m[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</a>';return $key;
  },$value)??$value;
  $v=htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $v=preg_replace('/\x60([^\x60]+)\x60/u','<code>$1</code>',$v)??$v;
  $v=preg_replace('/\*\*(.+?)\*\*/u','<strong>$1</strong>',$v)??$v;
  $v=preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u','<em>$1</em>',$v)??$v;
  $v=nl2br($v,false);foreach($tokens as $key=>$html)$v=str_replace($key,$html,$v);foreach($mediaHtml as $key=>$html)$v=str_replace($key,$html,$v);return $v;
 }
}
