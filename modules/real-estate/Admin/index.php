<?php
declare(strict_types=1);
$reStats=$reManager->stats();
$reFilters=[
 'status'=>(string)($_GET['re_status']??'all'),
 'listing_type'=>(string)($_GET['re_type']??''),
 'property_type'=>(string)($_GET['re_property']??''),
 'city'=>(string)($_GET['re_city']??''),
 'district'=>(string)($_GET['re_district']??''),
 'q'=>(string)($_GET['re_q']??''),
];
if(!in_array($reFilters['status'],['all','published','draft'],true))$reFilters['status']='all';
if(!in_array($reFilters['listing_type'],['','sale','rent'],true))$reFilters['listing_type']='';
if(!in_array($reFilters['property_type'],['','apartment','villa','house','land','commercial','office','other'],true))$reFilters['property_type']='';
$reListings=$reManager->listings($reFilters);
$reAgents=$reManager->agents();
$reLeads=$reManager->leads();
$reEdit=isset($_GET['edit_listing'])?$reManager->findListing((int)$_GET['edit_listing']):null;
$reEditAgent=null;
if(isset($_GET['edit_agent']))foreach($reAgents as $candidate)if((int)$candidate['id']===(int)$_GET['edit_agent']){$reEditAgent=$candidate;break;}
?>
<section class="content-admin"><h2><?= $adminText('re.admin.title','Emlak yönetimi') ?></h2><p><?= $adminText('re.admin.intro','İlanları, danışmanları ve web sitesi üzerinden gelen müşteri taleplerini yönetin.') ?></p>
<div class="module-grid">
<?php foreach([['listings',$reStats['listings'],'re.admin.stat_listings','Toplam ilan'],['published',$reStats['published'],'re.admin.stat_published','Yayındaki ilan'],['agents',$reStats['agents'],'re.admin.stat_agents','Aktif danışman'],['leads',$reStats['leads'],'re.admin.stat_leads','Yeni müşteri talebi']] as $stat): ?><article class="module-card"><small><?= $adminText($stat[2],$stat[3]) ?></small><h2><?= (int)$stat[1] ?></h2></article><?php endforeach ?>
</div></section>
<section class="content-admin"><h2><?= $reEdit?$adminText('re.admin.edit_listing','İlanı düzenle'):$adminText('re.admin.new_listing','Yeni ilan ekle') ?></h2>
<form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="re_listing_save"><input type="hidden" name="id" value="<?= (int)($reEdit['id']??0) ?>">
<label><?= $adminText('re.admin.listing_title','İlan başlığı') ?><input name="title" required maxlength="255" value="<?= $h($reEdit['title']??'') ?>"></label>
<label><?= $adminText('re.admin.slug','SEO bağlantısı') ?><input name="slug" required maxlength="190" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= $h($reEdit['slug']??'') ?>" placeholder="ankara-cankaya-satilik-daire"></label>
<div class="form-grid"><label><?= $adminText('re.admin.listing_type','İlan türü') ?><select name="listing_type"><option value="sale" <?= ($reEdit['listing_type']??'sale')==='sale'?'selected':'' ?>><?= $adminText('re.admin.sale','Satılık') ?></option><option value="rent" <?= ($reEdit['listing_type']??'')==='rent'?'selected':'' ?>><?= $adminText('re.admin.rent','Kiralık') ?></option></select></label>
<label><?= $adminText('re.admin.property_type','Gayrimenkul tipi') ?><select name="property_type"><?php foreach(['apartment'=>'Daire','villa'=>'Villa','house'=>'Müstakil ev','land'=>'Arsa','commercial'=>'Ticari','office'=>'Ofis','other'=>'Diğer'] as $v=>$label): ?><option value="<?= $v ?>" <?= ($reEdit['property_type']??'apartment')===$v?'selected':'' ?>><?= $adminText('re.admin.type_'.$v,$label) ?></option><?php endforeach ?></select></label>
<label><?= $adminText('re.admin.price','Fiyat') ?><input type="number" min="0" step="0.01" name="price" required value="<?= $h($reEdit['price']??'') ?>"></label>
<label><?= $adminText('re.admin.currency','Para birimi') ?><select name="currency"><?php foreach(['TRY','USD','EUR','GBP'] as $cur): ?><option <?= ($reEdit['currency']??'TRY')===$cur?'selected':'' ?>><?= $cur ?></option><?php endforeach ?></select></label>
<label><?= $adminText('re.admin.city','Şehir') ?><input name="city" required value="<?= $h($reEdit['city']??'') ?>"></label>
<label><?= $adminText('re.admin.district','İlçe') ?><input name="district" required value="<?= $h($reEdit['district']??'') ?>"></label>
<label><?= $adminText('re.admin.neighborhood','Mahalle') ?><input name="neighborhood" value="<?= $h($reEdit['neighborhood']??'') ?>"></label>
<label><?= $adminText('re.admin.agent','Danışman') ?><select name="agent_id"><option value="0">—</option><?php foreach($reAgents as $agent): if($agent['status']!=='active')continue; ?><option value="<?= (int)$agent['id'] ?>" <?= (int)($reEdit['agent_id']??0)===(int)$agent['id']?'selected':'' ?>><?= $h($agent['name']) ?></option><?php endforeach ?></select></label>
<?php foreach(['bedrooms'=>'Oda sayısı','bathrooms'=>'Banyo','area_gross'=>'Brüt m²','area_net'=>'Net m²','building_age'=>'Bina yaşı','floor_number'=>'Bulunduğu kat','total_floors'=>'Kat sayısı'] as $field=>$label): ?><label><?= $adminText('re.admin.'.$field,$label) ?><input type="number" min="0" max="100000" name="<?= $field ?>" value="<?= $h($reEdit[$field]??'') ?>"></label><?php endforeach ?>
<label><?= $adminText('re.admin.heating','Isıtma') ?><input name="heating" value="<?= $h($reEdit['heating']??'') ?>"></label>
<label><?= $adminText('re.admin.cover_image','Kapak görseli URL / yolu') ?><input name="cover_image" maxlength="500" value="<?= $h($reEdit['cover_image']??'') ?>"></label></div>
<label><?= $adminText('re.admin.address','Açık adres') ?><input name="address" maxlength="500" value="<?= $h($reEdit['address']??'') ?>"></label>
<label><?= $adminText('re.admin.description','İlan açıklaması') ?><textarea name="description" rows="8" required><?= $h($reEdit['description']??'') ?></textarea></label>
<label><?= $adminText('re.admin.features','Özellikler (her satıra bir özellik)') ?><textarea name="features" rows="4"><?= $h(implode("\n",$reEdit['features']??[])) ?></textarea></label>
<label><?= $adminText('re.admin.gallery','Galeri görselleri (her satıra bir HTTPS URL veya site içi yol)') ?><textarea name="gallery" rows="3"><?= $h(implode("\n",$reEdit['gallery']??[])) ?></textarea></label>
<label><?= $adminText('re.admin.seo_title','SEO başlığı') ?><input name="seo_title" maxlength="255" value="<?= $h($reEdit['seo_title']??'') ?>"></label>
<label><?= $adminText('re.admin.seo_description','SEO açıklaması') ?><textarea name="seo_description" rows="2" maxlength="320"><?= $h($reEdit['seo_description']??'') ?></textarea></label>
<div class="form-grid"><label><?= $adminText('re.admin.status','Yayın durumu') ?><select name="status"><option value="draft" <?= ($reEdit['status']??'draft')==='draft'?'selected':'' ?>><?= $adminText('re.admin.draft','Taslak') ?></option><option value="published" <?= ($reEdit['status']??'')==='published'?'selected':'' ?>><?= $adminText('re.admin.published','Yayında') ?></option></select></label><label><input type="checkbox" name="featured" value="1" <?= !empty($reEdit['featured'])?'checked':'' ?>> <?= $adminText('re.admin.featured','Öne çıkar') ?></label></div>
<button class="button"><?= $adminText('re.admin.save_listing','İlanı kaydet') ?></button><?php if($reEdit): ?> <a class="button secondary" href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=real_estate')) ?>"><?= $adminText('re.admin.cancel','Vazgeç') ?></a><?php endif ?></form></section>
<?php if($reEdit): ?>
<section class="content-admin">
<h2>İlan fotoğrafları</h2>
<p>Bilgisayarınızdan veya telefonunuzdan birden fazla fotoğraf yükleyebilirsiniz. Her görsel en fazla 8 MB olabilir (JPEG, PNG, GIF veya WebP). İlk yüklenen fotoğraf, kapak görseli boşsa otomatik kapak yapılır.</p>
<?php if(isset($_GET['uploaded'])): ?><p class="success">Fotoğraflar galeriye eklendi.</p><?php endif ?>
<form method="post" enctype="multipart/form-data" class="editor-form">
<?= CoreCMS\Core\Csrf::field() ?>
<input type="hidden" name="action" value="re_listing_media_upload">
<input type="hidden" name="id" value="<?= (int)$reEdit['id'] ?>">
<label>Fotoğrafları seçin<input type="file" name="listing_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required></label>
<button class="button" type="submit">Fotoğrafları yükle</button>
</form>
<?php if($reEdit['gallery']): ?>
<div class="module-grid">
<?php foreach($reEdit['gallery'] as $imageUrl): ?>
<article class="module-card"><img src="<?= $h($imageUrl) ?>" alt="İlan galerisi" loading="lazy" style="width:100%;max-height:160px;object-fit:cover;border-radius:8px"><small><?= $h($imageUrl) ?></small></article>
<?php endforeach ?>
</div>
<p>Kapak fotoğrafını değiştirmek, görselleri kaldırmak veya sıralamak için yukarıdaki ilan formunun <strong>Kapak görseli</strong> ve <strong>Galeri görselleri</strong> alanlarını düzenleyip ilanı kaydedin.</p>
<?php endif ?>
</section>
<?php endif ?>
<section class="content-admin"><h2><?= $adminText('re.admin.listing_list','İlan portföyü') ?></h2>
<form method="get" class="editor-form" aria-label="İlan portföyü filtreleri">
<input type="hidden" name="section" value="real_estate">
<div class="form-grid">
<label>İlan ara<input name="re_q" maxlength="150" value="<?= $h($reFilters['q']) ?>" placeholder="Başlık, açıklama veya mahalle"></label>
<label>Yayın durumu<select name="re_status"><?php foreach(['all'=>'Tümü','published'=>'Yayında','draft'=>'Taslak'] as $v=>$label): ?><option value="<?= $v ?>" <?= $reFilters['status']===$v?'selected':'' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
<label>İlan türü<select name="re_type"><?php foreach([''=>'Tümü','sale'=>'Satılık','rent'=>'Kiralık'] as $v=>$label): ?><option value="<?= $v ?>" <?= $reFilters['listing_type']===$v?'selected':'' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
<label>Gayrimenkul tipi<select name="re_property"><?php foreach([''=>'Tümü','apartment'=>'Daire','villa'=>'Villa','house'=>'Müstakil ev','land'=>'Arsa','commercial'=>'Ticari','office'=>'Ofis','other'=>'Diğer'] as $v=>$label): ?><option value="<?= $v ?>" <?= $reFilters['property_type']===$v?'selected':'' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
<label>Şehir<input name="re_city" maxlength="100" value="<?= $h($reFilters['city']) ?>"></label>
<label>İlçe<input name="re_district" maxlength="100" value="<?= $h($reFilters['district']) ?>"></label>
</div>
<div><button class="button" type="submit">Filtrele</button> <a class="button secondary" href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=real_estate')) ?>">Filtreleri temizle</a></div>
</form>
<p><?= count($reListings) ?> ilan listeleniyor (en fazla 100 sonuç).</p><div class="table-wrap"><table><thead><tr><th><?= $adminText('re.admin.listing_title','İlan') ?></th><th><?= $adminText('re.admin.location','Konum') ?></th><th><?= $adminText('re.admin.price','Fiyat') ?></th><th><?= $adminText('re.admin.status','Durum') ?></th><th><?= $adminText('admin.common.actions','İşlemler') ?></th></tr></thead><tbody><?php foreach($reListings as $listing): ?><tr><td><?= $h($listing['title']) ?></td><td><?= $h($listing['district'].' / '.$listing['city']) ?></td><td><?= number_format((float)$listing['price'],0,',','.').' '.$h($listing['currency']) ?></td><td><?= $adminText('re.admin.'.$listing['status'],$listing['status']) ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=real_estate&edit_listing='.(int)$listing['id'])) ?>"><?= $adminText('admin.common.edit','Düzenle') ?></a> · <a target="_blank" rel="noopener" href="<?= $h(CoreCMS\Core\Url::to('/emlak/ilan/'.$listing['slug'])) ?>"><?= $adminText('re.admin.view','Görüntüle') ?></a><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="re_listing_duplicate"><input type="hidden" name="id" value="<?= (int)$listing['id'] ?>"><button class="link-button" title="Taslak olarak yeni ilan oluştur">Kopyala</button></form> · <form method="post" class="inline-form" onsubmit="return confirm(<?= $adminJs('re.admin.confirm_delete_listing','Bu ilan silinsin mi?') ?>)"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="re_listing_delete"><input type="hidden" name="id" value="<?= (int)$listing['id'] ?>"><button class="link-button danger"><?= $adminText('admin.common.delete','Sil') ?></button></form></td></tr><?php endforeach ?></tbody></table></div><?php if(!$reListings): ?><p><?= $adminText('re.admin.no_listings','Henüz ilan eklenmemiş.') ?></p><?php endif ?></section>
<section class="content-admin"><h2><?= $reEditAgent?$adminText('re.admin.edit_agent','Danışmanı düzenle'):$adminText('re.admin.new_agent','Danışman ekle') ?></h2><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="re_agent_save"><input type="hidden" name="id" value="<?= (int)($reEditAgent['id']??0) ?>"><label><?= $adminText('re.admin.agent_name','Ad soyad') ?><input name="name" required maxlength="160" value="<?= $h($reEditAgent['name']??'') ?>"></label><div class="form-grid"><label><?= $adminText('admin.common.email','E-posta') ?><input type="email" name="email" value="<?= $h($reEditAgent['email']??'') ?>"></label><label><?= $adminText('re.admin.phone','Telefon') ?><input name="phone" value="<?= $h($reEditAgent['phone']??'') ?>"></label><label><?= $adminText('re.admin.agent_title','Unvan') ?><input name="title" value="<?= $h($reEditAgent['title']??'') ?>"></label><label><?= $adminText('re.admin.photo','Fotoğraf URL / yolu') ?><input name="photo_url" value="<?= $h($reEditAgent['photo_url']??'') ?>"></label></div><label><?= $adminText('re.admin.bio','Biyografi') ?><textarea name="bio" rows="3"><?= $h($reEditAgent['bio']??'') ?></textarea></label><label><?= $adminText('re.admin.status','Durum') ?><select name="status"><option value="active" <?= ($reEditAgent['status']??'active')==='active'?'selected':'' ?>><?= $adminText('re.admin.active','Aktif') ?></option><option value="inactive" <?= ($reEditAgent['status']??'')==='inactive'?'selected':'' ?>><?= $adminText('re.admin.inactive','Pasif') ?></option></select></label><button class="button"><?= $adminText('re.admin.save_agent','Danışmanı kaydet') ?></button></form>
<div class="table-wrap"><table><thead><tr><th><?= $adminText('re.admin.agent_name','Danışman') ?></th><th><?= $adminText('re.admin.phone','Telefon') ?></th><th><?= $adminText('re.admin.status','Durum') ?></th><th><?= $adminText('admin.common.actions','İşlemler') ?></th></tr></thead><tbody><?php foreach($reAgents as $agent): ?><tr><td><?= $h($agent['name']) ?></td><td><?= $h($agent['phone']) ?></td><td><?= $h($agent['status']) ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=real_estate&edit_agent='.(int)$agent['id'])) ?>"><?= $adminText('admin.common.edit','Düzenle') ?></a><form method="post" class="inline-form" onsubmit="return confirm(<?= $adminJs('re.admin.confirm_delete_agent','Bu danışman silinsin mi? İlanlar korunur.') ?>)"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="re_agent_delete"><input type="hidden" name="id" value="<?= (int)$agent['id'] ?>"><button class="link-button danger"><?= $adminText('admin.common.delete','Sil') ?></button></form></td></tr><?php endforeach ?></tbody></table></div></section>
<?php if($roleManager->can($role,'leads.manage')): ?><section class="content-admin"><h2><?= $adminText('re.admin.leads','Müşteri talepleri') ?></h2><div class="table-wrap"><table><thead><tr><th><?= $adminText('re.admin.date','Tarih') ?></th><th><?= $adminText('re.admin.agent_name','Müşteri') ?></th><th><?= $adminText('admin.common.email','E-posta') ?></th><th><?= $adminText('re.admin.phone','Telefon') ?></th><th><?= $adminText('re.admin.listing_title','İlan') ?></th><th><?= $adminText('re.admin.message','Mesaj') ?></th><th><?= $adminText('re.admin.status','Durum') ?></th></tr></thead><tbody><?php foreach($reLeads as $lead): ?><tr><td><?= $h($lead['created_at']) ?></td><td><?= $h($lead['name']) ?></td><td><a href="mailto:<?= $h($lead['email']) ?>"><?= $h($lead['email']) ?></a></td><td><?= $h($lead['phone']) ?></td><td><?= $h($lead['listing_title']??'—') ?></td><td><?= $h($lead['message']) ?></td><td><form method="post"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="re_lead_status"><input type="hidden" name="id" value="<?= (int)$lead['id'] ?>"><select name="status"><?php foreach(['new'=>'Yeni','contacted'=>'İletişime geçildi','closed'=>'Kapandı','spam'=>'Spam'] as $v=>$label): ?><option value="<?= $v ?>" <?= $lead['status']===$v?'selected':'' ?>><?= $adminText('re.admin.lead_'.$v,$label) ?></option><?php endforeach ?></select><button class="button secondary"><?= $adminText('admin.common.save','Kaydet') ?></button></form></td></tr><?php endforeach ?></tbody></table></div><?php if(!$reLeads): ?><p><?= $adminText('re.admin.no_leads','Henüz müşteri talebi yok.') ?></p><?php endif ?></section><?php endif ?>
