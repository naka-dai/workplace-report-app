# Kaizen Minimal (Heroku + Salesforce) — Starter Overlay

これは **既存の Laravel 11 プロジェクト** に重ねて配置する「最小構成」ファイル群です。
Heroku で動作し、**不具合報告フォーム → Salesforce カスタムオブジェクト作成 + 写真を Files 添付** を行います。

> この ZIP は *Laravel 本体* を含みません。先に Laravel を作成し、その上に配置してください。

---

## 0. 前提

- PHP 8.2+ / Laravel 11
- Heroku（PHP Buildpack）
- Salesforce Connected App（JWT Bearer Flow または Username-Password Flow ではなく **JWT 推奨**）
- Salesforce 側：カスタムオブジェクト `Defect_Report__c`（項目は必要に応じて調整）
- 画像は `ContentVersion`＋`FirstPublishLocationId` で対象レコードに紐付けます

---

## 1. Laravel プロジェクトの用意（ローカル）

```bash
# Laravel 11 プロジェクト作成（未作成の場合）
composer create-project laravel/laravel kaizen-minimal
cd kaizen-minimal

# 必要パッケージ（Guzzle）
composer require guzzlehttp/guzzle:^7
```

---

## 2. 本 ZIP の展開

この ZIP の中身を、作成した Laravel プロジェクト直下に **上書きコピー** してください。  
（`app/Services/`, `app/Http/Controllers/`, `routes/web.php`, `config/`, `resources/views/`, `Procfile` などが追加されます）

```
your-laravel/
 ├ app/
 │  ├ Http/
 │  │  ├ Controllers/
 │  │  │   └ DefectController.php
 │  │  └ Middleware/
 │  │      └ BasicAuth.php
 │  └ Services/
 │      └ SalesforceClient.php
 ├ bootstrap/
 ├ config/
 │  └ salesforce.php
 ├ public/
 ├ resources/
 │  └ views/
 │      ├ layout.blade.php
 │      └ defect_form.blade.php
 ├ routes/
 │  └ web.php   ← 既存を置き換え（必要なら手動マージ）
 ├ Procfile
 └ README.md（このファイル）
```

---

## 3. `.env` 設定（ローカルと Heroku 共通）

`.env` に以下を追加してください（Heroku では **Config Vars** として設定）。

```env
# ===== Basic Auth（簡易保護；任意） =====
BASIC_AUTH_USER=kaizen
BASIC_AUTH_PASS=change-me

# ===== Salesforce (JWT Bearer Flow) =====
SF_LOGIN_URL=https://login.salesforce.com
SF_AUDIENCE=https://login.salesforce.com
SF_CLIENT_ID=<ConnectedApp_ConsumerKey>
SF_USERNAME=<integration_user_login@example.com>
# 改行を含むPEM秘密鍵を base64 でエンコードして設定（Herokuにそのまま貼れるようにするため）
SF_PRIVATE_KEY_B64=<base64_of_your_RSA_private_key_pem>

# ===== カスタム（必要に応じて） =====
SF_API_VERSION=v61.0
SF_DEFECT_OBJECT=Defect_Report__c
MAX_PHOTO_MB=8
```

> **秘密鍵の取り扱い**：`SF_PRIVATE_KEY_B64` には `-----BEGIN PRIVATE KEY----- ...` を base64 化した文字列を設定してください。  
> 例：`cat private.pem | base64 -w0` で作成（macOSは `-b 0`）。

---

## 4. ミドルウェア登録（BasicAuth）

`app/Http/Kernel.php` に **ミドルウェアエイリアス** を追加：

```php
protected $routeMiddleware = [
    // 既存...
    'basic.env' => \App\Http\Middleware\BasicAuth::class,
];
```

---

## 5. 動作確認（ローカル）

```bash
php artisan serve
# http://127.0.0.1:8000/ にアクセス
# Basic 認証が出たら .env のユーザー/パスを入力
```

- フォームに不具合内容と写真を選択 → 送信すると、
  - Salesforce に `Defect_Report__c` が 1件作成
  - 同レコードへ写真が Files として紐付く（ContentVersion + FirstPublishLocationId）

---

## 6. Heroku デプロイ

```bash
# 初回のみ
heroku create <your-app-name> --stack heroku-22
heroku buildpacks:set heroku/php

# 環境変数（Config Vars）設定
heroku config:set BASIC_AUTH_USER=kaizen BASIC_AUTH_PASS=change-me
heroku config:set SF_LOGIN_URL=https://login.salesforce.com
heroku config:set SF_AUDIENCE=https://login.salesforce.com
heroku config:set SF_CLIENT_ID=xxxxx
heroku config:set SF_USERNAME=integration@example.com
heroku config:set SF_PRIVATE_KEY_B64=<base64_pem>
heroku config:set SF_API_VERSION=v61.0 SF_DEFECT_OBJECT=Defect_Report__c MAX_PHOTO_MB=8

# デプロイ（Gitで）
git init
git add .
git commit -m "kaizen minimal starter"
git push heroku main   # または 'git push heroku HEAD:main'
```

デプロイ後、`https://<your-app-name>.herokuapp.com/` にアクセスしてフォーム送信を確認してください。

---

## 7. カスタマイズ

- 入力項目のマッピング：`app/Http/Controllers/DefectController.php` の `$defectPayload` を調整
- 画像制限：`.env` の `MAX_PHOTO_MB`、およびコントローラのバリデーションを変更
- ログ：`storage/logs/laravel.log` を参照（Heroku では `heroku logs -t`）

---

## 8. 注意事項

- これは **最小構成** です。再送キュー・監査ログ・CSRF/Rate Limit などは必要に応じて拡張してください。
- 大容量画像は端末側で圧縮してから送ると安定します（モバイル回線対策）。
- 本番では Basic 認証だけでなく、SSO/IdP連携や組織内VPNなどで更に保護することを推奨します。

---

Happy shipping! 🚀
