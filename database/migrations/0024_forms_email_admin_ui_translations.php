<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.nav.forms" => ["Formlar", "Forms"],
        "admin.nav.submissions" => ["Form gönderileri", "Submissions"],
        "admin.nav.email_templates" => ["E-posta şablonları", "Email templates"],
        "admin.permission.forms_manage" => ["Formları ve e-posta şablonlarını yönetme", "Manage forms and email templates"],
        "admin.forms.migration_required" => ["Formlar için veritabanı güncellemesi gerekli", "A database update is required for forms"],
        "admin.forms.edit" => ["Formu düzenle", "Edit form"],
        "admin.forms.new" => ["Form oluştur", "Create form"],
        "admin.forms.key" => ["Form anahtarı", "Form key"],
        "admin.forms.name" => ["Form adı", "Form name"],
        "admin.forms.description" => ["Açıklama", "Description"],
        "admin.forms.recipient" => ["Bildirim alıcısı", "Notification recipient"],
        "admin.forms.success_message" => ["Başarı mesajı", "Success message"],
        "admin.forms.fields_json" => ["Alanlar (JSON)", "Fields (JSON)"],
        "admin.forms.fields_help" => ["Desteklenen türler: text, email, tel, textarea, select ve checkbox. Select alanları options dizisi gerektirir.", "Supported types: text, email, tel, textarea, select and checkbox. Select fields require an options array."],
        "admin.forms.enabled" => ["Etkin", "Enabled"],
        "admin.forms.disabled" => ["Devre dışı", "Disabled"],
        "admin.forms.list" => ["Formlar", "Forms"],
        "admin.forms.status" => ["Durum", "Status"],
        "admin.forms.view_submissions" => ["Gönderileri görüntüle", "View submissions"],
        "admin.forms.confirm_delete" => ["Bu form ve gönderileri silinsin mi?", "Delete this form and its submissions?"],
        "admin.forms.empty" => ["Henüz form oluşturulmadı.", "No forms have been created yet."],
        "admin.forms.fields_json_invalid" => ["Form alanları geçerli bir JSON dizisi olmalıdır.", "Form fields must be a valid JSON array."],
        "admin.submissions.title" => ["Form gönderileri", "Form submissions"],
        "admin.submissions.form" => ["Form", "Form"],
        "admin.submissions.date" => ["Alınma tarihi", "Received"],
        "admin.submissions.language" => ["Dil", "Language"],
        "admin.submissions.data" => ["Gönderilen veriler", "Submitted data"],
        "admin.submissions.status" => ["Durum", "Status"],
        "admin.submissions.new" => ["Yeni", "New"],
        "admin.submissions.read" => ["Okundu", "Read"],
        "admin.submissions.archived" => ["Arşivlendi", "Archived"],
        "admin.submissions.empty" => ["Henüz form gönderisi alınmadı.", "No form submissions have been received yet."],
        "admin.email.migration_required" => ["E-posta şablonları için veritabanı güncellemesi gerekli", "A database update is required for email templates"],
        "admin.email.create_base_first" => ["Çeviri eklemeden önce e-posta şablonunu varsayılan dilde oluşturun.", "Create the email template in the default language before adding translations."],
        "admin.email.edit" => ["E-posta şablonunu düzenle", "Edit email template"],
        "admin.email.new" => ["E-posta şablonu oluştur", "Create email template"],
        "admin.email.key" => ["Şablon anahtarı", "Template key"],
        "admin.email.name" => ["Şablon adı", "Template name"],
        "admin.email.subject" => ["Konu", "Subject"],
        "admin.email.body" => ["HTML e-posta içeriği", "HTML email body"],
        "admin.email.placeholders" => ["{{name}}, {{email}} ve {{site_name}} gibi yer tutucular kullanın. Yer tutucu değerleri HTML olarak kaçırılır.", "Use placeholders such as {{name}}, {{email}} and {{site_name}}. Placeholder values are HTML-escaped."],
        "admin.email.list" => ["E-posta şablonları", "Email templates"],
        "admin.email.empty" => ["Henüz e-posta şablonu oluşturulmadı.", "No email templates have been created yet."]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
