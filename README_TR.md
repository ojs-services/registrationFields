# Kayıt Formu Alanları Eklentisi — OJS 3.3

OJS kullanıcı kayıt formuna **ORCID iD** ve **Telefon Numarası** alanları ekler. Kayıt sırasında girilen bilgiler doğrudan kullanıcı profiline kaydedilir — çekirdek dosyalarda değişiklik yapılmaz.

## Neden Bu Eklenti?

OJS'de ORCID iD ve telefon numarası kullanıcı profilinde zaten mevcuttur, ancak bu alanlar yalnızca kayıt *sonrasında* profil düzenleyici üzerinden doldurulabilir. Bu durum hem kullanıcılar hem de dergi yöneticileri için ek bir adım oluşturur. Bu eklenti, söz konusu alanları doğrudan kayıt formuna taşıyarak verilerin en başından itibaren toplanmasını sağlar.

## Özellikler

- Kayıt formunda **ORCID iD alanı** (format doğrulamalı)
- Kayıt formunda **Telefon Numarası alanı** (format doğrulamalı)
- Her alan bağımsız olarak **etkinleştirilebilir veya devre dışı bırakılabilir**
- Her alan **zorunlu veya isteğe bağlı** olarak ayarlanabilir
- Her iki alan için **sunucu tarafı doğrulama**
- Kabul edilen ORCID formatları: `0000-0000-0000-0000`, `https://orcid.org/…`, `http://orcid.org/…` — kayıt sırasında otomatik olarak HTTPS'ye normalleştirilir
- Veriler mevcut OJS kullanıcı profiline yazılır (Profil > Genel > ORCID iD / Profil > İletişim > Telefon)
- OJS temalarıyla uyumlu (Default, Manuscript, Bootstrap3, Health Sciences)
- İki dilli arayüz: İngilizce ve Türkçe
- Çekirdek dosyalarda değişiklik yok — tamamen hook tabanlı mimari

## Gereksinimler

| Gereksinim | Sürüm |
|---|---|
| OJS | 3.3.0.0 – 3.3.0.22 |
| PHP | 7.4 – 8.2 |

## Kurulum

1. En son `.tar.gz` dosyasını [Releases](https://github.com/ojs-services/registrationFields/releases) sayfasından indirin.
2. OJS'de **Ayarlar → Web Sitesi → Eklentiler → Yeni Eklenti Yükle** bölümüne gidin.
3. `.tar.gz` dosyasını yükleyin.
4. **Genel Eklentiler** listesinde eklentiyi etkinleştirin.
5. Alanları yapılandırmak için eklenti adının yanındaki **Ayarlar** bağlantısına tıklayın.

### Manuel Kurulum

1. Arşivi `plugins/generic/registrationFields/` dizinine çıkarın.
2. Şablon önbelleğini temizleyin: `cache/t_compile/` içindeki tüm dosyaları silin.
3. Eklenti yönetim sayfasından eklentiyi etkinleştirin.

## Yapılandırma

Eklentiyi etkinleştirdikten sonra **Ayarlar**'a tıklayarak yapılandırma penceresine erişin:

| Ayar | Açıklama |
|---|---|
| ORCID iD alanını etkinleştir | Kayıt formunda ORCID iD alanını gösterir |
| ORCID iD zorunlu olsun | ORCID iD'yi kayıt için zorunlu kılar |
| Telefon Numarası alanını etkinleştir | Kayıt formunda telefon numarası alanını gösterir |
| Telefon Numarası zorunlu olsun | Telefon numarasını kayıt için zorunlu kılar |
| Hata ayıklama modu | Enjeksiyon tanılamasını PHP hata günlüğüne yazar |

Bir alan etkinleştirilmediğinde, ilgili "Zorunlu" seçeneği otomatik olarak devre dışı kalır.

## Tema Uyumluluğu

Eklenti, kayıt formu HTML'inde enjeksiyon noktalarını bulmak için kelime sınırı (word boundary) içeren esnek regex kalıpları kullanır. Bu sayede form elemanlarına satır içi stil (inline style), birden fazla CSS sınıfı veya ek öznitelik ekleyen temalarla uyumluluk sağlanır.

Eklenti bilinen bir referans noktası (`login` fieldset, `consent` fieldset veya `buttons` div) bulamazsa, alanları kapanan `</form>` etiketinin hemen öncesine ekler. Bu, kullanılan temadan bağımsız olarak alanların her zaman görünür olmasını garanti eder.

Hata ayıklama modu etkinleştirildiğinde, eklenti hangi enjeksiyon noktasının kullanıldığını veya yedek mekanizmanın devreye girip girmediğini PHP hata günlüğüne yazar. Bu, temaya özgü sorunları gidermek için kullanışlıdır.

## Nasıl Çalışır?

Eklenti, kayıt iş akışıyla entegrasyon için OJS hook sistemini kullanır:

1. **Alan enjeksiyonu** — Çıktı filtresi, HTML alanlarını render edilmiş kayıt formuna profil ve giriş bölümleri arasına yerleştirir.
2. **Veri yakalama** — `registrationform::readuserdata` hook'u gönderilen değerleri okur.
3. **Doğrulama** — `registrationform::validate` hook'u regex kalıp eşleştirmesiyle sunucu tarafı doğrulama yapar.
4. **Kaydetme** — `registrationform::execute` hook'u alan değerlerini yakalar ve kullanıcı kaydı oluşturulduktan sonra ertelenmiş bir geri çağırma yoluyla kaydeder.

## Alan Doğrulama

### ORCID iD
- Üç format kabul edilir: yalın (`0000-0000-0000-0000`), HTTPS URL veya HTTP URL
- Desen: `XXXX-XXXX-XXXX-XXXY` (X: rakam, Y: rakam veya `X`)
- Kayıt sırasında otomatik olarak `https://orcid.org/…` formatına normalleştirilir
- Maksimum 37 karakter

### Telefon Numarası
- Rakam, boşluk, `+`, `-`, `(`, `)`, `.` karakterleri kullanılabilir
- Uzunluk: 5–24 karakter
- Maksimum 24 karakter

## Güvenlik

- Tüm kullanıcı girdileri HTML'de görüntülenmeden önce `htmlspecialchars()` ile kaçırılır (escape)
- Her iki alan için katı regex desenleriyle sunucu tarafı doğrulama
- Ayarlar formunda OJS'nin yerleşik `{csrf}` belirteci ile CSRF koruması
- Eklenti ayarlarına yalnızca Dergi Yöneticisi rolüne sahip kullanıcılar erişebilir
- Ham SQL sorgusu kullanılmaz — tüm veritabanı işlemleri OJS'nin DAO katmanı üzerinden yapılır
- Girdi uzunluk limitleri hem istemci tarafında (`maxlength`) hem sunucu tarafında uygulanır

## Dosya Yapısı

```
registrationFields/
├── RegistrationFieldsPlugin.inc.php   Ana eklenti sınıfı
├── index.php                          Eklenti yükleyici
├── version.xml                        Sürüm ve uyumluluk
├── LICENSE                            GPL v3
├── README.md                          İngilizce dokümantasyon
├── README_TR.md                       Türkçe dokümantasyon
├── templates/
│   └── settingsForm.tpl               Ayarlar modal şablonu
└── locale/
    ├── en_US/locale.po                İngilizce çeviriler
    └── tr_TR/locale.po                Türkçe çeviriler
```

## Lisans

Bu eklenti [GNU Genel Kamu Lisansı v3](https://www.gnu.org/licenses/gpl-3.0.en.html) kapsamında lisanslanmıştır.

## Geliştirici

[OJS Services](https://ojs-services.com) tarafından geliştirilmiştir.
