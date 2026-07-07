# LLMs.txt Generator for WP

WordPressサイトのコンテンツから llms.txt を自動生成するプラグインです。ChatGPTやClaude、PerplexityなどのAI・大規模言語モデル（LLM）がサイト内容を理解しやすいMarkdown形式で、サイトの構成とコンテンツの概要を1ファイルにまとめて出力します（LLMO / AI検索対策）。

![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)

## 📋 主な機能

- **自動生成**: 投稿・固定ページの公開、更新、削除時に自動で llms.txt を更新(1リクエストにつき1回だけ実行される軽量設計)
- **ワンボタン操作**: 「設定を保存してLLMS.txtを生成」ボタンひとつで全設定の保存とファイル生成が完了(画面下部に固定表示)
- **プレビュー**: 生成された llms.txt の内容を設定画面でそのまま確認可能
- **出力対象の選択**: 出力する投稿タイプ・固定ページをチェックボックスで選択(公開設定の投稿タイプのみが対象)
- **並び替え**: 出力順序をドラッグ&ドロップまたは矢印ボタンで変更可能
- **カテゴリ分類**: ブログ投稿はカテゴリ別に自動分類して出力
- **一覧ページの出力**: アーカイブを持つカスタム投稿タイプは一覧ページのURLも出力
- **賢い要約**: 「手動抜粋 → SEOプラグイン(Yoast SEO / Rank Math)のメタディスクリプション → 本文の冒頭」の優先順で要約を自動採用
- **カスタムテキスト**: ファイル上部に表示するテキストをMarkdownで自由に設定
- **文字コード選択**: UTF-8(デフォルト)またはShift-JISで出力

## 🚀 インストール

### 方法1: ZIPファイルでインストール

1. このリポジトリから最新版をダウンロード
2. WordPress管理画面の「プラグイン」→「新規追加」→「プラグインのアップロード」
3. ZIPファイルをアップロードして有効化

### 方法2: 手動インストール

1. このリポジトリをダウンロードまたはクローン

```bash
git clone https://github.com/KuboFumiaki/llms-txt-generator-wp.git
```

2. プラグインフォルダを `/wp-content/plugins/` にアップロード
3. WordPress管理画面でプラグインを有効化

## ⚙️ 使い方

1. プラグインを有効化すると、初回の llms.txt が自動生成されます
2. 管理画面の「ツール」→「LLMs.txt Generator for WP」にアクセス
   - ページ上部でファイルの状態(URL・最終更新・出力順序)とプレビューを確認できます
3. 出力内容をカスタマイズ:
   - **カスタムテキスト**: ファイル上部に表示するテキスト(Markdown可)
   - **文字コード**: UTF-8またはShift-JIS
   - **投稿タイプ**: 出力する投稿タイプと順序
   - **固定ページ**: 出力する固定ページと順序
4. 画面下部の「設定を保存してLLMS.txtを生成」をクリック
5. `https://yoursite.com/llms.txt` でファイルを確認

新しく固定ページを公開すると自動で出力対象に追加されます。出力したくないページは設定画面でチェックを外してください。

## 📄 生成されるファイル形式

```markdown
# サイト名

サイトの説明

※ここより上部はカスタムテキストで自由に変更できます※

# 最終更新: 2026-07-07 12:00:00

## 固定ページ
- [会社概要](https://example.com/about/):ページの要約...
  - [アクセス](https://example.com/about/access/):ページの要約...

## 投稿

### カテゴリ名
- [記事タイトル](https://example.com/post-slug/):記事の要約...

## お知らせ（カスタム投稿タイプ）
- [お知らせ一覧](https://example.com/news/)
- [お知らせタイトル](https://example.com/news/slug/):記事の要約...
```

- 固定ページは親子関係を保ったまま階層表示されます
- アーカイブを持つカスタム投稿タイプは見出し直下に一覧ページのURLが入ります
- 各記事の要約は「手動抜粋 → SEOプラグインのメタディスクリプション → 本文の冒頭」の優先順で自動採用されます

## 🔧 システム要件

- **WordPress**: 5.0以上(7.0対応済み)
- **PHP**: 7.4以上
- **拡張機能**: mbstring(Shift-JIS出力を使う場合)

## 🛡️ セキュリティ

- nonce検証によるCSRF対策
- 権限チェック(`manage_options` 権限が必要)
- 入力データのサニタイズと出力エスケープ
- 公開設定(public)の投稿タイプのみを出力対象とし、内部用データの漏えいを防止
- ファイル書き込みは WP_Filesystem API を使用
- アンインストール時に設定データと生成ファイルを完全削除

## 📁 ファイル構成

```
llms-txt-generator-wp/
├── index.php          # メインプラグインファイル
├── js/
│   └── admin.js       # 管理画面用スクリプト（並び替え・一括選択）
├── uninstall.php      # アンインストール処理
├── readme.txt         # WordPress Plugin Directory用
├── README.md          # GitHub用説明ファイル
└── .gitignore         # Git除外設定
```

## 🔄 更新履歴

### v1.0.0 (2026-07-07)

- 初回リリース
- llms.txt の自動生成(公開・更新・削除時)と手動生成
- 生成内容のプレビュー表示
- 出力する投稿タイプ・固定ページの選択と並び替え(ドラッグ&ドロップ対応)
- カスタム投稿タイプの一覧(アーカイブ)ページの出力
- カテゴリ別の投稿分類
- 要約の自動採用(手動抜粋 → Yoast SEO / Rank Math のメタディスクリプション → 本文冒頭)
- カスタムテキスト設定
- 文字コード選択(UTF-8 / Shift-JIS)

## 🤝 貢献方法

1. このリポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/amazing-feature`)
3. 変更をコミット (`git commit -m 'Add some amazing feature'`)
4. ブランチにプッシュ (`git push origin feature/amazing-feature`)
5. プルリクエストを作成

## 🐛 バグ報告・機能リクエスト

バグ報告や機能リクエストは [Issues](https://github.com/KuboFumiaki/llms-txt-generator-wp/issues) でお願いします。

## 📄 ライセンス

このプラグインは [GPL v2](https://www.gnu.org/licenses/gpl-2.0.html) ライセンスの下で公開されています。

---

**LLMs.txt Generator for WP** - AI時代のWordPressコンテンツ管理を支援します 🤖
