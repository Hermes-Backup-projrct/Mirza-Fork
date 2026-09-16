# 🌐 راهنمای نصب میرزا بات روی هاست cPanel

این راهنما مخصوص **هاست اشتراکی cPanel** است (سرور اختصاصی/VPS نیست).
پروژه از طریق نصب‌کنندهٔ تحت وب خود ربات (`install/index.php`) نصب می‌شود؛
این فایل فقط مراحل آماده‌سازی هاست و کرون‌ها را توضیح می‌دهد.

---

## ✅ پیش‌نیازها

| مورد | جزئیات |
|------|--------|
| **PHP** | ۸.۲ یا بالاتر (۸.۲ تا ۸.۴ پیشنهاد می‌شود) |
| **دیتابیس** | MySQL 5.7.8+ یا MariaDB 10.2+ |
| **اکستنشن‌ها** | `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl` |
| **گزینه‌ها** | `allow_url_fopen`, `exec` (برای اجرای کرون PHP) |
| **دامنه + SSL** | الزامی — تلگرام فقط وبهوک `https` می‌پذیرد |
| **Composer** | نیاز به ساخت پوشهٔ `vendor/` (در صورت نبودن، راهی زیر آمده) |

> ⚠️ بدون SSL دامنه، وبهوک تلگرام کار **نمی‌کند**. ابتدا Let's Encrypt را فعال کنید.

---

## ۱) ساخت دیتابیس

وارد cPanel شوید → **MySQL® Database Wizard**:

1. نام دیتابیس: مثلاً `mirza_bot` → نام کامل می‌شود `user_mirza_bot`
2. ساخت کاربر: `mirza_user` + پسورد قوی (ذخیره کنید)
3. **All Privileges** را برای کاربر روی دیتابیس بدهید

> 📝 سه مقدار `user_mirza_bot`، `mirza_user` و پسورد را برای مرحلهٔ ۵ نگه دارید.

---

## ۲) تنظیم نسخهٔ PHP

**cPanel → Software → Select PHP Version**

- نسخه: **8.2** (یا 8.3 / 8.4)
- تیک این اکستنشن‌ها را بزنید:
  - `pdo_mysql`, `mysqli`, `curl`, `mbstring`, `json`, `openssl`, `fileinfo`, `zip`, `gd`
- در تب **Options** مقدار `max_execution_time` را روی **300** و `memory_limit` روی **512M** تنظیم کنید.

---

## ۳) آپلود فایل‌ها

**cPanel → Files → File Manager** (یا FTP):

1. به مسیر `public_html/` (یا ساب‌دامنه/پوشهٔ دامنه) بروید.
2. **قبل از آپلود**، اگر از حالت فشرده استفاده می‌کنید:
   - فایل ZIP پروژه را آپلود کنید → دکمهٔ **Extract**
   - محتویات باید **مستقیماً داخل `public_html`** قرار گیرند، نه در یک زیرپوشه.
   - یعنی `public_html/index.php`، نه `public_html/mirza/index.php`
3. اگر ZIP ندارید، همهٔ فایل‌ها را دستی آپلود کنید (شامل پوشه‌های `install`, `db`, `cronbot`, `lang`, `api`, `panel`, `payment`, `app`, `ibsng` و فایل‌های ریشه).

> 🔒 پس از extract، پوشه و فایل‌های زیر باید **سطح دسترسی** مشخصی داشته باشند:
>
> | مسیر | سطح |
> |------|-----|
> | پوشهٔ اصلی `public_html` | **755** |
> | `config.php` | **644** |
> | پوشهٔ `install/state` | **775** |
> | سایر فایل‌های `.php` | **644** |

---

## ۴) ساخت پوشهٔ vendor (کتابخانه‌ها)

ربات به `vendor/autoload.php` نیاز دارد (`endroid/qr-code` و `phpoffice/phpspreadsheet`).
در cPanel سه راه وجود دارد:

**راه اول — Composer در ترمینال (اگر SSH/terminal باز است):**
```bash
cd ~/public_html
composer install --no-dev --optimize-autoloader --prefer-dist
```

**راه دوم — Terminal داخل cPanel:**
cPanel → **Advanced → Terminal** → دستورات بالا را اجرا کنید.

**راه سوم — اجرای محلی و آپلود:**
اگر Composer روی سیستم خودتان هست:
```bash
composer install --no-dev --optimize-autoloader --prefer-dist
```
سپس کل پوشهٔ `vendor/` ساخته‌شده را به `public_html/vendor` آپلود کنید.

> ⚠️ بدون `vendor/autoload.php` ربات خطای Fatal Error می‌دهد.

---

## ۵) اجرای نصب‌کنندهٔ تحت وب

مرورگر را باز کنید و به این آدرس بروید:

```
https://دامنه-شما/install/
```

نصب‌کننده مراحل زیر را به‌صورت گرافیکی (فارسی) طی می‌کند:

1. **بررسی پیش‌نیازها** — نسخهٔ PHP، اکستنشن‌ها، دسترسی‌ها، HTTPS
2. **تأیید توکن ربات** — توکن را از `@BotFather` وارد کنید
3. **ورود اطلاعات دیتابیس** — میزبان `localhost` + مقادیر مرحلهٔ ۱
4. **اطلاعات ربات** — شناسهٔ عددی ادمین (از `@userinfobot`) و نام دامنه
5. **نوشتن `config.php`** — خودکار
6. **ساخت جداول** — اجرای `db/bootstrap.php` (جداول + ایندکس‌ها + مهاجرت‌ها)
7. **تنظیم وبهوک** — در پایان نصب، پوشهٔ `install` خودکار حذف می‌شود

> 📌 اگر نصب‌کننده نتوانست پوشهٔ `install` را حذف کند، خودتان از File Manager
> پوشهٔ `public_html/install` را حذف کنید و یک بار آدرس `https://دامنه/table.php`
> را در مرورگر باز کنید تا وبهوک ست شود.

---

## ۶) تنظیم کرون‌ها (مهم)

روی هاست اشتراکی شما `shell_exec` معمولاً بسته است، بنابراین ربات
**نمی‌تواند کرون‌ها را خودش ثبت کند**. این کار را دستی انجام دهید:

**cPanel → Advanced → Cron Jobs**

در بخش **Add New Cron Job**، تک‌تک خطوط زیر را اضافه کنید.
(دامنه و مسیر را با مقادیر خودتان عوض کنید.)

```cron
*/1 * * * * curl -s https://دامنه-شما/cronbot/croncard.php > /dev/null 2>&1
*/1 * * * * curl -s https://دامنه-شما/cronbot/NoticationsService.php > /dev/null 2>&1
*/1 * * * * curl -s https://دامنه-شما/cronbot/sendmessage.php > /dev/null 2>&1
*/1 * * * * curl -s https://دامنه-شما/cronbot/activeconfig.php > /dev/null 2>&1
*/1 * * * * curl -s https://دامنه-شما/cronbot/disableconfig.php > /dev/null 2>&1
*/1 * * * * curl -s https://دامنه-شما/cronbot/iranpay1.php > /dev/null 2>&1
*/2 * * * * curl -s https://دامنه-شما/cronbot/gift.php > /dev/null 2>&1
*/2 * * * * curl -s https://دامنه-شما/cronbot/configtest.php > /dev/null 2>&1
*/3 * * * * curl -s https://دامنه-شما/cronbot/plisio.php > /dev/null 2>&1
*/5 * * * * curl -s https://دامنه-شما/cronbot/payment_expire.php > /dev/null 2>&1
*/15 * * * * curl -s https://دامنه-شما/cronbot/statusday.php > /dev/null 2>&1
*/15 * * * * curl -s https://دامنه-شما/cronbot/on_hold.php > /dev/null 2>&1
*/15 * * * * curl -s https://دامنه-شما/cronbot/uptime_node.php > /dev/null 2>&1
*/15 * * * * curl -s https://دامنه-شما/cronbot/uptime_panel.php > /dev/null 2>&1
*/30 * * * * curl -s https://دامنه-شما/cronbot/expireagent.php > /dev/null 2>&1
0 */5 * * * curl -s https://دامنه-شما/cronbot/backupbot.php > /dev/null 2>&1
```

**اختیاری** (فقط اگر قرعه‌کشی/امتیازات را فعال کرده‌اید):
```cron
*/1 * * * * curl -s https://دامنه-شما/cronbot/lottery.php > /dev/null 2>&1
```

> 💡 اگر `curl` روی هاست در دسترس نیست، از اجرای مستقیم PHP استفاده کنید:
> ```cron
> */1 * * * * /usr/local/bin/php /home/یوزر/public_html/cronbot/croncard.php > /dev/null 2>&1
> ```
> مسیر `/usr/local/bin/php` و `/home/یوزر` را با توجه به هاست خود تنظیم کنید.
> (مسیر دقیق php را از `which php` در Terminal cPanel بگیرید.)

---

## ۷) فعال‌سازی ربات

پس از حذف پوشهٔ `install`:

1. آدرس زیر را یک‌بار در مرورگر باز کنید:
   ```
   https://دامنه-شما/table.php
   ```
   این کار جداول نهایی + وبهوک را ست می‌کند.
2. در تلگرام ربات را `/start` کنید.

---

## ⚠️ مشکلات رایج

| مشکل | راه‌حل |
|------|--------|
| **سفید بالا می‌آید / ارور 500** | `display_errors` را در cPanel → **Select PHP Version → Options** فعال کنید تا ارور نمایش داده شود. رایج‌ترین علت: نبود `vendor/autoload.php` یا PHP زیر ۸.۲. |
| **`error: database connection failed`** | مقادیر `config.php` را چک کنید. روی cPanel میزبان معمولاً `localhost` است، نه آی‌پی. |
| **وبهوک ست نمی‌شود** | دامنه باید `https` فعال داشته باشد. در cPanel → **SSL/TLS Status** چک کنید. همچنین آی‌پی سرور نباید توسط تلگرام مسدود شده باشد. |
| **ربات جواب نمی‌دهد ولی کرون اجرا می‌شود** | آدرس `https://دامنه-شما/table.php` را در مرورگر باز کنید. اگر ارور داد، `error_log` را در File Manager ببینید. |
| **کرون‌ها اجرا نمی‌شوند** | در cPanel → **Cron Jobs**، ایمیل کرون را تنظیم کنید تا خروجی برایتان بیاید. یا خروجی را به یک فایل لاگ بفرستید (`>> /home/یوزر/cron.log 2>&1`). |
| **`Unauthorized access` در مرورگر** | طبیعی است — `index.php` فقط آی‌پی تلگرام را می‌پذیرد. ربات را از تلگرام `/start` کنید. |
| **دسترسی فایل‌ها پس از آپلود خراب است** | در File Manager → Permissions، فایل‌ها را **644** و پوشه‌ها را **755** کنید. |

---

## 🔄 آپدیت کردن

1. از cPanel → **Backup** یک بکاپ کامل بگیرید.
2. فایل‌های جدید را آپلود و extract کنید، **به‌جز**:
   - `config.php` (تنظیمات شماست)
3. اگر فایل ZIP نسخهٔ جدید `install/` داشت و قبلاً نصب کرده‌اید، پوشهٔ `install` را
   **حذف کنید** — در غیر این صورت ربات مسدود می‌شود (توسط `.htaccess`).
4. اگر پوشهٔ `install` وجود دارد و می‌خواهید نصب را مجدد اجرا کنید:
   فایل `install/.installed` را حذف کنید.
5. آدرس `https://دامنه-شما/table.php` را باز کنید تا مهاجرت‌های دیتابیس اجرا شوند.
6. کرون‌های جدید را در صورت اضافه شدن، از بخش ۶ اضافه کنید.

---

## 🔒 نکات امنیتی

- پوشهٔ `install` را **بعد از نصب حذف کنید**. تا زمانی که وجود دارد، `.htaccess`
  ریشه، درخواست‌ها را مسدود می‌کند (خطای 403).
- `config.php` حاوی رمز دیتابیس و توکن ربات است — سطح دسترسی **644** و هرگز آن را
  به‌صورت عمومی منتشر نکنید.
- فایل‌های `.htaccess` پروژه را حذف **نکنید**؛ از دانلود شدن فایل‌های `.json`،
  `.sql` و `error_log` جلوگیری می‌کنند.
- گزینهٔ `Register Globals` و `allow_url_include` باید **خاموش** باشند.
- `exec`/`shell_exec` را فقط در صورت نیاز باز کنید؛ کرون‌ها با `curl` کار می‌کنند.

---

## 📂 ساختار مهم برای آپلود

```
public_html/
├── index.php              # نقطهٔ ورود وبهوک تلگرام
├── config.php             # تنظیمات (با نصب‌کننده ساخته می‌شود)
├── table.php              # ست کردن جداول + وبهوک
├── admin.php              # وب‌پنل ادمین
├── function.php           # توابع اصلی
├── panels.php             # لایهٔ پنل‌ها (شامل HMPanel)
├── hmpanel.php            # لایهٔ API پنل HMPanel
├── botapi.php             # ارتباط با تلگرام
├── vendor/                # کتابخانه‌های Composer (باید ساخته شود)
├── install/               # نصب‌کننده (بعد از نصب حذف شود)
├── db/                    # جداول + مهاجرت‌ها
├── cronbot/               # اسکریپت‌های کرون
├── lang/                  # ترجمه‌ها (fa/en/ru/zh)
├── api/, panel/, payment/, app/, ibsng/
└── .htaccess              # امنیت — حذف نکنید
```
