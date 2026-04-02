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

---

## APIテスト + バグ修正 (2026-04-02)

### テスト実施
全33 APIエンドポイントを42回のHTTPコールでカバー。テスト対象:
- 認証（admin/poster/無トークン）: 200/200/401
- 投稿者API全GET: categories, posts, posts/{id} → 全200
- 権限制御: poster→admin API→403、L1→publish→403
- 管理者API全GET: dashboard, posts, users, categories, settings, notifications → 全200
- 管理操作: approve(200), reject(200), updateLevel(200), updateCategory(200)
- 投稿作成: POST /api/posts → 202
- 投稿削除: DELETE /api/posts/{id} → 200
- WP接続テスト: POST /admin/settings/test-wp → 200
- WPカテゴリ同期: POST /admin/categories/sync → 200 (3件同期)
- WPカテゴリ作成: POST /admin/categories → 201
- 課金: billing/status → trial表示、billing/checkout → Stripe未設定503
- バリデーション: rejected post→publish → 422、投稿ありカテゴリ削除 → 422
- WP直接: ping → 200、投稿作成 → 201

### 発見・修正したバグ 4件

**Bug 1: Dockerネットワーク（データ修正）**
テナントの`wp_site_url`が`http://localhost:8082`でDockerコンテナ内から到達不可。
→ `http://host.docker.internal:8082`に変更。本番では実ドメインが入るため問題なし。

**Bug 2: WPプラグイン `wp_insert_category()` 未定義（コード修正）**
- ファイル: `sakusaku-post-bridge/includes/class-sakusaku-category-sync.php`
- 原因: `wp_insert_category()`は`wp-admin/includes/taxonomy.php`にあるadmin専用関数。REST APIコンテキストでは自動ロードされない。
- 修正: `wp_insert_term($name, 'category', $args)`に置換。term_exists時の既存ID返却も対応。
- 関連修正: `class-sakusaku-api.php` のレスポンス形式を`['id' => $termId, 'term_id' => $termId]`に統一。

**Bug 3: WpBridgeService Guzzle空配列バグ（コード修正）**
- ファイル: `sakusaku-service/app/Services/WpBridgeService.php`
- 原因: `Http::get($url, [])`でGuzzleが既存の`?rest_route=`クエリ文字列を上書き。WPのHTMLトップページが返り、JSON APIに到達しない。
- 修正: `empty($data)`の場合は引数なしで`$client->$method($url)`を呼ぶ。
- 影響: getCategories()で0件返却 → カテゴリ同期が常に0件だった。

**Bug 4: PHP 8.3 + Laravel 13互換性（環境修正）**
- ファイル: `sakusaku-service/Dockerfile`
- 原因: `composer create-project`でLaravel 13.2.0がインストールされた（プラン時点ではLaravel 12想定）。Laravel 13はSymfony HttpFoundation 8.0に依存し、PHP 8.4の`request_parse_body()`を使用。PHP 8.3ではPUT/DELETEリクエストでFatal error。
- 修正: Dockerfile `php:8.3-fpm` → `php:8.4-fpm`。イメージ再ビルド+コンテナ再起動。

### 修正後のCategoryManagementController::store()
WPにカテゴリを先に作成してwp_category_idを取得し、updateOrCreateでLaravel側に保存する方式に変更。WP接続失敗時は502を返す（ローカルだけに中途半端に作らない）。

---

## Vite本番ビルド + コード監査 + セキュリティ修正 (2026-04-03)

### Vite本番ビルド
- `npm run build` 成功（469ms、23アセット出力）
- 出力先: `public/build/` (gitignored、デプロイ時に都度ビルド)
- 最大バンドル: runtime-core 55.75KB(gzip 21.91KB)、useApi 36.56KB(gzip 14.50KB)

### コード監査（15件発見）
CRITICAL 1件、HIGH 4件、MEDIUM 5件、LOW 5件を検出。

**修正済み（CRITICAL+HIGH 3件）:**
1. **WebhookController: Stripe署名検証なし（CRITICAL）** — `Stripe\Webhook::constructEvent()`による署名検証を追加。STRIPE_WEBHOOK_SECRET未設定時はローカル開発として検証スキップ。
2. **PostManagementController::publish(): WP Bridge例外未捕捉（HIGH）** — try/catchで502レスポンスを返すよう修正。
3. **EnsureTenantActive: テナント未解決時500→403（HIGH）** — セキュリティ的に適切なステータスコードに変更。

**要対応（HIGH残り2件、MEDIUM 5件）:**
- ProcessDocSubmission: リトライ時のステータス保持ロジック整理
- GoogleDocsService: OAuthトークンリフレッシュのロギングとマージン
- ProcessDocSubmission: 画像アップロード失敗時のHTML更新ロジック
- ProcessDocSubmission: TagGenerator config値の明示的な受け渡し（現状はデフォルト値が一致しているため実害なし）
- WebhookController: DB::transaction()ラップ
- GoogleDocsService: Http::retry()の追加
- EnsureTenantActive → PostManagementController: 明示的なテナント所有権検証（middleware依存で実害なし）

**対応不要（LOW 5件）:** 空ドキュメントバリデーション、config起動時検証、extractDocId URL対応拡張、ImageProcessingService例外処理、routes/web.phpルート順序コメント

### 追加修正（MEDIUM 3件、2026-04-03）
- ProcessDocSubmission: 画像プレースホルダーのフォールバック処理追加（未処理プレースホルダーを「[画像を処理できませんでした]」に置換）
- GoogleDocsService: 全fetchメソッドにHttp::retry(2, 1000)追加
- ProcessDocSubmission: リトライ回数をadmin_commentに表示（attempt N/3形式）

### WPパーマリンク有効化（2026-04-03）
- `.htaccess`にRewriteルール追加
- WP DB `permalink_structure` を `/%postname%/` に変更
- `/wp-json/sakusaku/v1/ping` 形式で動作確認済み
- `?rest_route=` 形式も引き続き動作

### キューワーカー確認
- ProcessDocSubmissionジョブがFAIL → テスト用偽Docs URL(`1TestDoc123`)でGoogle Docsアクセス404。期待通り。
- Post status=failed, admin_comment記録済み → エラーハンドリング正常。

### 本番デプロイ準備（2026-04-03）
- `docker/nginx.conf`: PHP-FPM + SPA routing + 静的アセットキャッシュ
- `docker-compose.prod.yml`: nginx + php-fpm + worker + scheduler構成
- `.env.example`: `SAKUSAKU_STRIPE_PRICE_ID`追加
- Laravelキャッシュコマンド（config:cache, route:cache, view:cache）動作確認済み
- スケジューラ（tenants:process-expired-trials）動作確認済み
- 空ドキュメントバリデーション追加
- ImageProcessingService: ファイル存在チェック追加

### 全コミット履歴（11 commits on main）
```
e8944e5 fix: Add empty doc validation and image file existence check
c270c3b feat: Add production deployment config (nginx, docker-compose.prod.yml)
e947430 docs: Final BUILD_LOG update with all fixes, audit results, permalink setup
15b6237 fix: Add HTTP retry to Google Docs API calls
13fcea6 fix: Replace unprocessed image placeholders with fallback text
58f329d fix: Improve job retry messaging and OAuth token refresh reliability
35fb5da docs: Add Vite build, code audit results, and security fix details
84ae673 fix: Security and error handling improvements from code audit
5f145a9 fix: 4 bugs found during API testing (PHP 8.4, Guzzle, WP plugin, Docker)
a0c2460 feat: Complete Steps 7-10 (Vue SPA, Admin, Tags, Billing)
3b44f27 feat: Initial implementation Steps 1-7 (backend)
```
