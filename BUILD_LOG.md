# SakuSaku Post for WP — Build Log

## Step 1: Scaffolding (完了 2026-04-02)
- Laravel 12プロジェクト作成 (`sakusaku-service/`)
- Dockerfile (PHP 8.3-fpm + MeCab + GD + Redis)
- Docker Compose (app:8083, worker, node:5173, scheduler)
- MySQL: `sakusaku_post` DB + `sakusakuuser` on port 3307
- Redis: Difyの`docker_default`ネットワーク経由、DB番号2
- WPプラグイン骨格 (`sakusaku-post-bridge/`): 11エンドポイント、APIキー認証、設定画面
- PHP依存: sanctum, socialite, cashier, intervention/image, guzzle
- 検証: `curl localhost:8083` → 200、`migrate` → 成功

## Step 2: DB + Models (完了 2026-04-02)
- 14テーブル: tenants, users, sessions, categories, posts, post_images, post_tags, tag_corpus, activity_log, personal_access_tokens, subscriptions, subscription_items, cache, jobs
- 10 Eloquentモデル: Tenant, User, Post, Category, PostImage, PostTag, TagCorpus, ActivityLog, Subscription, SubscriptionItem
- 5 Enum: TenantStatus, DocsRetrievalMethod, UserRole, UserLevel, PostStatus
- DemoTenantSeeder: 1テナント、2ユーザー、3カテゴリ、3投稿
- 検証: `migrate:fresh --seed` 成功、リレーション・Enumキャスト正常動作

## Step 3: Auth (完了 2026-04-02)
- GoogleAuthController (redirect + callback → Sanctumトークン発行)
- LogoutController
- 4ミドルウェア: ResolveTenant, EnsureTenantActive, EnsureAdminRole, EnsureUserLevel
- TenantContext シングルトンサービス
- routes/api.php: `/api/me` + admin/billing グループ骨格
- config/services.php: Google OAuth設定
- 検証: `/api/me` トークンあり→200+JSON、トークンなし→401

## Step 4: WP Plugin (完了 2026-04-02)
- 骨格はStep 1で作成済み（11エンドポイント、認証、投稿/メディア/カテゴリ/タグ操作、設定画面）
- WPテスト環境(localhost:8082, WP 6.9.4)にインストール・有効化
- APIキー設定済み、パーマリンク: `?rest_route=`形式で動作（.htaccess未設定のため）
- 検証: ping→200 `{"status":"ok","version":"1.0.0","wp":"6.9.4"}`
- 検証: 投稿作成→201 `{"wp_post_id":6,"preview_url":"...?p=6&preview=true"}`
- 検証: 不正APIキー→401、キーなし→401

## Step 5: Docs取得 + HTML変換 (完了 2026-04-02)
- GoogleDocsService: 3方式対応（URL直接/OAuth+トークンリフレッシュ/SA+JWT認証）
- DocsHtmlConverter: DOMDocumentベースHTML変換
  - H1→タイトル抽出、span→semantic変換(strong/em/u)、img→プレースホルダー
  - 属性ホワイトリスト、空段落除去、連続br制限
- WpBridgeService: Laravel→WPプラグインHTTPクライアント（全11メソッド）
- extractDocId(): URLからドキュメントID抽出
- 検証: Google Docs風HTMLを変換→タイトル抽出OK、太字/斜体変換OK、画像プレースホルダーOK、不要属性除去OK

## Step 6: 画像パイプライン (完了 2026-04-02)
- ImageProcessingService: DL→MIME検証→Intervention Imageでリサイズ(max 1600px)→temp保存
  - JPEG/PNG/GIF/WebP対応、20MB上限、アスペクト比維持
- ProcessDocSubmissionジョブ: パイプライン全体オーケストレーター（1ジョブ方式）
  - Docs取得→HTML変換→画像処理→WP下書き作成→画像WPアップロード→プレースホルダー置換→featured image設定
  - 画像個別try/catch（1枚失敗しても他は続行）
  - 失敗時: status→failed、admin_commentにエラー詳細
  - timeout 5分、retry 3回（60s/300s/900s backoff）

## Step 7: 投稿者API (バックエンド部分完了 2026-04-02)
- PostController: index(一覧+ページネーション)/store(送信→ジョブ発行)/show/destroy/publish
  - poster→自分の投稿のみ、admin→全投稿閲覧可
  - L2+でpublish可（user-levelミドルウェア）
  - 削除時WP下書きも連動削除
- CategoryController: index（テナントのアクティブカテゴリ一覧）
- SubmitPostRequest: URL検証(regex)、カテゴリ存在確認、コメント文字数制限
- PostResource/CategoryResource: APIレスポンス整形（リレーション含む）
- 検証: GET /api/categories→3件、GET /api/posts→3件(pagination)、GET /api/posts/1→詳細JSON
- **Vue SPAフロントエンドは未作成**（次セッションで実装）

## Step 8: 管理者ダッシュボード
- 未着手

## Step 9: タグ + 通知
- 未着手

## Step 10: 課金
- 未着手
