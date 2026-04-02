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
- Vue SPA作成完了: Login, Callback, Submit, MyPosts, PostDetail
- Pinia stores: auth, posts, categories
- useApi composable: Axiosラッパー+トークン自動付与+401ハンドリング
- Vue Router: 認証ガード、admin権限ガード、SPAキャッチオール
- レイアウト: PosterLayout（ナビ+ログアウト）、AdminLayout（インディゴナビ）

## Step 8: 管理者ダッシュボード (完了 2026-04-02)
- 6 APIコントローラ: Dashboard, PostManagement, UserManagement, CategoryManagement, NotificationSettings, TenantSettings
- 22 adminルート登録済み
- 7 Vue管理ページ: Dashboard, Posts, PostReview, Users, Categories, Notifications, Settings, Billing
- 検証: GET /api/admin/dashboard→stats JSON, /admin/posts→3件, /admin/users→2件, /admin/settings→テナント設定
- 検証: Poster→admin API→403（権限制御OK）

## Step 9: タグ + 通知 (完了 2026-04-02)
- MeCabTokenizer: proc_open経由でMeCab CLI呼び出し、名詞抽出（非自立/接尾/数/代名詞除外、2文字以上）
- TfIdfCalculator: TF(augmented)×IDF、tag_corpusテーブルでテナント別DF管理
- TagGeneratorService: テキスト→MeCab→TF-IDF→上位10件をpost_tagsに保存→corpus更新
- NotificationService: Google Chat(Cards API) + Teams(Adaptive Cards)へのWebhook送信
- ProcessDocSubmissionジョブにタグ生成(step 8)+通知(step 10)を組み込み
- 検証: MeCab動作確認（美容室テキスト→14名詞抽出）、タグ生成確認（10タグ+スコア出力）

## Step 10: 課金 (完了 2026-04-02)
- SubscriptionController: status(現在のプラン・トライアル残日数)/checkout(Stripe Session作成)/portal
- WebhookController: subscription.created/updated/deleted, invoice.payment_failed → テナントstatus自動更新
- ProcessExpiredTrials artisanコマンド: 毎日実行、期限切れトライアルをsuspendに
- config/sakusaku.php: Stripe Price ID、トライアル日数、画像設定、タグ設定の一元管理
- routes/console.php: スケジューラ登録
- routes/web.php: Stripe webhookエンドポイント（認証なし）、SPAキャッチオールをapp.blade.phpに切り替え
- 検証: GET /api/billing/status→trial状態表示OK、POST /api/billing/checkout→Stripe未設定エラー正常、artisan expired-trials→0件suspended

---

## 全Step完了 (2026-04-02)

### 残作業（Phase 1リリース前）
- Google OAuthのGCPクライアントID/Secret設定
- StripeのAPIキー + Price ID設定
- Vite本番ビルド (`npm run build`)
- 本番環境へのデプロイ設定（nginx reverse proxy等）
- WPプラグインのパーマリンク設定（.htaccess）
- E2Eテスト: Google Docsに記事作成→URL送信→WPに下書き→プレビュー→公開
