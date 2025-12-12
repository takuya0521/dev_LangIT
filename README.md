# LangIT (E-learning) Backend

Laravel 11 + Laravel Sail を使った e-learning バックエンド。  
マルチテナントは **C-1 方式（サブドメインごとに別 DB）** を採用。ベースドメインは `langit.local`。

---

## Requirements

推奨開発環境（現状の前提）

-   Windows 11
-   WSL2 (Ubuntu)
-   Docker Desktop
-   Git
-   Node.js / npm（Vite）
-   （任意）VSCode

このプロジェクトは **Laravel Sail** で動かす前提。

---

## Setup (First time)

> 以降のコマンドは、基本的に **WSL 上**で実行する想定（例：`/mnt/c/dev_LangIT`）。

### 1) Install dependencies

    composer install
    npm install

### 2) Environment

`.env` を作成して、最低限この値を設定：

-   `TENANT_BASE_DOMAIN=langit.local`（C-1 用ベースドメイン）
-   `TENANT_DB_DATABASE=demo_school_db`（例）
-   `JWT_SECRET=...`（JWT 用のランダムな長い文字列）

### 3) Start containers

    ./vendor/bin/sail up -d

### 4) Migrate (main DB)

    ./vendor/bin/sail artisan migrate

---

## Multi-tenant (C-1) Runbook

### 1) hosts 設定（Windows）

`C:\Windows\System32\drivers\etc\hosts` に追記：

    127.0.0.1 langit.local
    127.0.0.1 demo.langit.local

反映後：

    ipconfig /flushdns

（任意）WSL 側にも入れる場合：

    echo "127.0.0.1 demo.langit.local" | sudo tee -a /etc/hosts

### 2) demo テナント作成（tenants テーブル）

    ./vendor/bin/sail artisan db:seed --class=TenantSeeder

### 3) テナント DB 作成（MySQL 内）

    docker exec -it dev_langit-mysql-1 mysql -uroot -p
    # パスワードは .env の DB_PASSWORD（例：password）

    CREATE DATABASE IF NOT EXISTS demo_school_db
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;

    GRANT ALL PRIVILEGES ON demo_school_db.* TO 'sail'@'%';
    FLUSH PRIVILEGES;
    exit;

### 4) テナント切替の動作確認

`/tenant-test` で現在接続中の tenant DB 名を返す簡易ルートがある想定。

-   `http://localhost/tenant-test` → `Tenant DB: tenant_dummy`（Host により切替が効かない場合）
-   `http://demo.langit.local/...` のように **Host が demo サブドメイン**になるアクセスで tenant 解決・接続切替を行う想定

---

## Major APIs

### Auth

-   `POST /api/auth/login`
-   `POST /api/auth/password`（要 JWT）

### Student (role:student)

-   `GET /api/progress-rate`
-   `GET /api/courses`
-   `GET /api/courses/{course}`

### Video (F03)

-   `GET /api/video?video_id={id}`
-   `POST /api/videos/{video}/event`（play/pause/seek + position）
-   `POST /api/videos/{video}/complete`（watch_time）

### Test (F05)

-   `GET /api/tests/{test}`
-   `POST /api/tests/{test}/score`（normal / review）

### Question Tags

-   `GET /api/question-tags`（keyword 検索あり）
-   `GET /api/question-tags/stats?test_id=&course_id=&chapter_id=&days=`

### Admin (role:admin)

-   `GET /api/admin/users`
-   `POST /api/admin/users`
-   `PUT /api/admin/users/{id}`
-   `PUT /api/admin/users/{id}/password`

### Admin: Question Tags

-   `GET /api/admin/question-tags`
-   `POST /api/admin/question-tags`
-   `PUT /api/admin/question-tags/{tag}`
-   `DELETE /api/admin/question-tags/{tag}`
-   `PUT /api/admin/tests/{test}/questions/{question}/tags`（問題へのタグ付け）

---

## Notes

-   `user_id` はクライアントから受け取らず、JWT から取得する設計。
-   動画の完了判定は **80%**（`VideoProgressService`）。

---

## README 更新 →Git に反映

    git checkout -b feature/readme
    git add README.md
    git commit -m "docs: update README"
    git push -u origin feature/readme
