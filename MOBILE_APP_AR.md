# تطبيق الجوال (Offline + Sync)

هذا المشروع أصبح جاهزًا للعمل كتطبيق جوال عبر **Capacitor** مع:

- حفظ الطلبات محليًا عند عدم وجود إنترنت.
- مزامنة تلقائية عند عودة الاتصال.
- مزامنة إضافية عند فتح التطبيق أو عودته إلى الواجهة.

## 1) التثبيت

```bash
npm install
```

## 2) ضبط رابط النظام

قبل أي أوامر Capacitor، حدّد رابط النظام المنشور:

### PowerShell

```powershell
$env:CAPACITOR_APP_URL="https://YOUR-DOMAIN.COM"
```

> الملف المستخدم: `capacitor.config.ts`

## 3) إنشاء مشروع الجوال

```bash
npx cap add android
npx cap add ios
```

## 4) مزامنة الملفات

```bash
npm run mobile:sync
```

## 5) فتح المشروع في Android Studio / Xcode

```bash
npm run mobile:android
npm run mobile:ios
```

## كيف تعمل المزامنة بدون إنترنت؟

- عند انقطاع الإنترنت، النماذج المعلّمة بـ `data-offline-queue="enabled"` تُخزَّن محليًا.
- عند عودة الإنترنت:
  - يتم استدعاء `syncQueue()` تلقائيًا.
  - يتم الإرسال أيضًا دوريًا كل دقيقتين.
  - داخل تطبيق الجوال، أضيف جسر Capacitor لالتقاط:
    - تغيّر الشبكة (`networkStatusChange`)
    - عودة التطبيق للواجهة (`appStateChange` / `resume`)
  - في كل هذه الحالات، تتم محاولة إرسال البيانات المعلقة تلقائيًا.

## ملاحظات مهمة

- يجب أن يكون رابط `CAPACITOR_APP_URL` رابطًا صحيحًا ويمكن الوصول له من الجهاز.
- للاختبارات المحلية على Android Emulator يمكن استخدام `http://10.0.2.2:8000` مع `cleartext`.
- يفضّل HTTPS في الإنتاج.
